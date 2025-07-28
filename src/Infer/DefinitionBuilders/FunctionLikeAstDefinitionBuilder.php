<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\SideEffectCallEvent;
use Dedoc\Scramble\Infer\Handler\IndexBuildingHandler;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\ArrayArgumentTypeBag;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Infer\Services\TemplateTypesSolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeAbstract;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

class FunctionLikeAstDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    private LazyShallowReflectionIndex $shallowIndex;

    /**
     * @param  IndexBuilder[]  $indexBuilders
     */
    public function __construct(
        public string $name,
        public FunctionLike $functionLike,
        public Index $index,
        public FileNameResolver $fileNameResolver,
        public ClassDefinition $classDefinition,
        public array $indexBuilders = [],
        public bool $withSideEffects = false,
    ) {
        $this->shallowIndex = app(LazyShallowReflectionIndex::class);
    }

    public function build(): FunctionLikeDefinition
    {
        $definition = $this->classDefinition->methods[$this->name] ?? new FunctionLikeDefinition(
            new FunctionType(name: $this->name),
        );

        $inferrer = $this->traverseClassMethod([$this->functionLike], $definition);

        $definition = $this->classDefinition->methods[$this->name];

        if ($this->functionLike instanceof ClassMethod) {
            $definition->selfOutType = $this->inferSelfOutType($inferrer, $this->functionLike);
        }

        if ($this->withSideEffects) {
            $this->analyzeSideEffects($definition, $inferrer);
        }

        //        static::resolveFunctionReturnReferences(new GlobalScope, $this->classDefinition->methods[$this->name]->type);
        //
        //        foreach ($this->classDefinition->methods[$this->name]->type->exceptions as $i => $exceptionType) {
        //            $this->classDefinition->methods[$this->name]->type->exceptions[$i] = (new ReferenceTypeResolver($this->index)) // @phpstan-ignore assign.propertyType
        //                ->resolve(new GlobalScope, $exceptionType);
        //        }

        $definition->isFullyAnalyzed = true;

        return $definition;
    }

    public static function resolveFunctionReturnReferences(Scope $scope, FunctionLikeType $functionType): void
    {
        $returnType = $functionType->getReturnType();
        $resolvedReference = ReferenceTypeResolver::getInstance()->resolve($scope, $returnType);
        $functionType->setReturnType($resolvedReference);

        if ($annotatedReturnType = $functionType->getAttribute('annotatedReturnType')) {
            if (! $functionType->getAttribute('inferredReturnType')) {
                $functionType->setAttribute('inferredReturnType', clone $functionType->getReturnType());
            }

            $functionType->setReturnType(
                self::addAnnotatedReturnType($functionType->getReturnType(), $annotatedReturnType, $scope)
            );
        }
    }

    private static function addAnnotatedReturnType(Type $inferredReturnType, Type $annotatedReturnType, Scope $scope): Type
    {
        $types = $inferredReturnType instanceof Union
            ? $inferredReturnType->types
            : [$inferredReturnType];

        // @todo: Handle case when annotated return type is union.
        if ($annotatedReturnType instanceof ObjectType) {
            $resolvedName = ReferenceTypeResolver::resolveClassName($scope, $annotatedReturnType->name);
            if (! $resolvedName) {
                throw new LogicException("Got null after class name resolution of [$annotatedReturnType->name], string expected");
            }
            $annotatedReturnType->name = $resolvedName;
        }

        $annotatedTypeCanAcceptAnyInferredType = collect($types)
            ->some(function (Type $t) use ($annotatedReturnType) {
                if ($annotatedReturnType->accepts($t)) {
                    return true;
                }

                return $t->acceptedBy($annotatedReturnType);
            });

        if (! $annotatedTypeCanAcceptAnyInferredType) {
            return $annotatedReturnType;
        }

        return Union::wrap($types)->mergeAttributes($inferredReturnType->attributes());
    }

    /**
     * @param  Node[]  $nodes
     */
    private function traverseClassMethod(array $nodes, FunctionLikeDefinition $methodDefinition): TypeInferer
    {
        $traverser = new NodeTraverser;

        $traverser->addVisitor($inferrer = new TypeInferer(
            $this->index,
            $this->fileNameResolver,
            new Scope($this->index, new NodeTypesResolver, new ScopeContext($this->classDefinition), $this->fileNameResolver),
            Context::getInstance()->extensionsBroker->extensions,
            [new IndexBuildingHandler($this->indexBuilders)],
        ));

        $node = (new NodeFinder)
            ->findFirst(
                $nodes,
                fn ($n) => $n instanceof ClassMethod && $n->name->toString() === $methodDefinition->type->name
            );

        $traverser->traverse(Arr::wrap($node));

        return $inferrer;
    }

    private function inferSelfOutType(TypeInferer $inferrer, ClassMethod $node): ?Type
    {
        $scope = $inferrer->getFunctionLikeScope($node);
        if (! $scope) {
            return null;
        }

        if (! $classDefinition = $scope->context->classDefinition) {
            return null;
        }

        if (! $functionDefinition = $scope->context->functionDefinition) {
            return null;
        }

        $expectedTemplatesMap = collect($classDefinition->templateTypes)
            ->mapWithKeys(fn (TemplateType $t) => [$t->name => null])
            ->all();

        $templateDefiningStatements = (new NodeFinder)->find(
            $node->stmts,
            fn ($n) => $this->isThisPropertyAssignment($n) // Direct assignments of something on `$this`, like `$this->foo = 42`.
                || ($functionDefinition->type->name === '__construct' && $this->isParentConstructCall($n)) // Calls to `parent::__construct` if is in constructor
                || ($this->isPotentialSetterCall($n) && $this->isSelfTypeOrCallOnSelfType($scope->getType($n->var)))// just any method call on $this (self type!)
        );

        foreach (array_reverse($templateDefiningStatements) as $statement) {
            if ($this->isThisPropertyAssignment($statement)) {
                $thisPropertiesAssignment = $statement;

                $propertyName = $thisPropertiesAssignment->var->name->name;

                if (! array_key_exists($propertyName, $classDefinition->properties)) {
                    continue;
                }

                // if property name is not template type - skip
                $propertyType = $classDefinition->properties[$propertyName]->type;
                if (! $propertyType instanceof TemplateType || ! array_key_exists($propertyType->name, $expectedTemplatesMap)) {
                    continue;
                }

                // if property's template type is defined - skip
                if ($expectedTemplatesMap[$propertyType->name] !== null) {
                    continue;
                }

                // if property template type equals to assigned expression template type - skip
                $assignedType = $scope->getType($thisPropertiesAssignment->expr);
                if ($propertyType === $assignedType) {
                    continue;
                }

                // define template
                $expectedTemplatesMap[$propertyType->name] = $assignedType;

                continue;
            }

            if ($this->isParentConstructCall($statement)) {
                $parentConstructCall = $statement;

                if (! $classDefinition->parentFqn) {
                    continue;
                }

                if (! $parentDefinition = $scope->index->getClass($classDefinition->parentFqn)) {
                    continue;
                }

                $parentConstructor = $parentDefinition->getMethodDefinition('__construct');
                if (
                    ! $parentConstructor
                    || ! isset($parentConstructor->selfOutType)
                    || ! $constructorSelfOutType = $parentConstructor->selfOutType
                ) {
                    continue;
                }

                $parentCallContextTemplates = (new TemplateTypesSolver)->getClassConstructorContextTemplates(
                    $parentDefinition,
                    $parentDefinition->getMethodDefinition('__construct'),
                    new ArrayArgumentTypeBag($scope->getArgsTypes($parentConstructCall->args)),
                );

                foreach ($constructorSelfOutType->templateTypes as $index => $genericSelfOutTypePart) {
                    if (! $definedParentTemplateType = ($parentDefinition->templateTypes[$index] ?? null)) {
                        continue;
                    }

                    // if property's template type is defined - skip
                    if (($expectedTemplatesMap[$definedParentTemplateType->name] ?? null) !== null) {
                        continue;
                    }

                    $concreteSelfOutTypePart = $genericSelfOutTypePart instanceof TemplatePlaceholderType && array_key_exists($definedParentTemplateType->name, $parentCallContextTemplates)
                        ? $parentCallContextTemplates[$definedParentTemplateType->name]
                        : (new TypeWalker)->map(
                            $genericSelfOutTypePart,
                            fn ($t) => $t instanceof TemplateType && array_key_exists($t->name, $parentCallContextTemplates)
                                ? $parentCallContextTemplates[$t->name]
                                : $t,
                        );

                    $expectedTemplatesMap[$definedParentTemplateType->name] = $concreteSelfOutTypePart;
                }

                continue;
            }

            // Potential setter calls analysis requires reference resolution!
            if ($this->isPotentialSetterCall($statement)) {
                $potentialSetterCall = $statement;

                /*
                 * When getting statements we made sure that `var` is either `$this`, or a call to
                 * a method on `$this` so resolving potential setter calls should not trigger entire codebase
                 * analysis (which would be slow).
                 */
                $var = ReferenceTypeResolver::getInstance()->resolve(
                    $scope,
                    $scope->getType($potentialSetterCall->var)->clone(),
                );

                if (! $var instanceof SelfType) {
                    continue;
                }

//                try {
//                    if (! Index::shouldAnalyzeAst((new \ReflectionClass($this->classDefinition->name))->getMethod($potentialSetterCall->name->name)->getFileName() ?: '')) {
//                        continue;
//                    }
//                } catch (\Throwable) {
//                    continue;
//                }

                if (! $methodDefinition = $classDefinition->getMethodDefinition($potentialSetterCall->name->name)) {
                    continue;
                }

                if (
                    ! $methodDefinition
                    || ! isset($methodDefinition->selfOutType)
                    || ! $methodSelfOutType = $methodDefinition->selfOutType
                ) {
                    continue;
                }

                $methodCallContextTemplates = (new TemplateTypesSolver)->getFunctionContextTemplates(
                    $methodDefinition,
                    new ArrayArgumentTypeBag($scope->getArgsTypes($potentialSetterCall->args)),
                );

                foreach ($methodSelfOutType->templateTypes as $index => $genericSelfOutTypePart) {
                    if ($genericSelfOutTypePart instanceof TemplatePlaceholderType) {
                        continue;
                    }

                    if (! $definedTemplateType = ($classDefinition->templateTypes[$index] ?? null)) {
                        continue;
                    }

                    // if property's template type is defined - skip
                    if (($expectedTemplatesMap[$definedTemplateType->name] ?? null) !== null) {
                        continue;
                    }

                    $concreteSelfOutTypePart = (new TypeWalker)->map(
                        $genericSelfOutTypePart,
                        fn ($t) => $t instanceof TemplateType && array_key_exists($t->name, $methodCallContextTemplates)
                            ? $methodCallContextTemplates[$t->name]
                            : $t,
                    );

                    $expectedTemplatesMap[$definedTemplateType->name] = $concreteSelfOutTypePart;
                }

                continue;
            }
        }

        return new Generic(
            'self',
            array_values(array_map(fn ($type) => $type ?: new TemplatePlaceholderType, $expectedTemplatesMap))
        );
    }

    private function analyzeSideEffects(FunctionLikeDefinition $methodDefinition, TypeInferer $inferrer): void
    {
        $fnScope = $inferrer->getFunctionLikeScope($this->functionLike);

        if (! $fnScope) {
            return;
        }

        foreach ($fnScope->getMethodCalls() as $methodCall) {
            match (true) {
                $methodCall instanceof MethodCall || $methodCall instanceof NullsafeMethodCall => $this->analyzeMethodCall($methodDefinition, $fnScope, $methodCall),
                $methodCall instanceof StaticCall => $this->analyzeStaticMethodCall($methodDefinition, $fnScope, $methodCall),
                $methodCall instanceof FuncCall => $this->analyzeFuncCall($methodDefinition, $fnScope, $methodCall),
                $methodCall instanceof New_ => null,
                default => null,
            };
        }
    }

    private function analyzeMethodCall(FunctionLikeDefinition $methodDefinition, Scope $fnScope, MethodCall|NullsafeMethodCall $methodCall): void
    {
        // 1. ensure method call should be handled
        /*
         * Only explicit method calls are supported. So the following is supported:
         *    $this->foo()
         * But when the expression is in place, we skip analysis:
         *     $this->{$var}()
         */
        if (! $methodCall->name instanceof Name && ! $methodCall->name instanceof Identifier) {
            return;
        }

        // 2. get called method definition and if not yet analyzed, analyze shallowly (PHPDoc, type hints)

        // get shallow method definition (get shallow callee type, get the shallow definition)
        $calleeType = (new ShallowTypeResolver($this->shallowIndex))->resolve($fnScope, $fnScope->getType($methodCall->var));
        if ($calleeType instanceof TemplateType && $calleeType->is) {
            $calleeType = $calleeType->is;
        }
        if (! $calleeType instanceof ObjectType) {
            return;
        }

        $definition = $this->shallowIndex->getClass($calleeType->name);
        if (! $definition) {
            return;
        }

        $shallowMethodDefinition = $definition->getMethod($methodCall->name->name);
        if (! $shallowMethodDefinition) {
            return;
        }

        $this->applySideEffectsFromCall(new SideEffectCallEvent(
            definition: $methodDefinition,
            calledDefinition: $shallowMethodDefinition,
            node: $methodCall,
            scope: $fnScope,
            arguments: new ArrayArgumentTypeBag($fnScope->getArgsTypes($methodCall->args)),
        ));
    }

    private function analyzeStaticMethodCall(FunctionLikeDefinition $methodDefinition, Scope $fnScope, StaticCall $methodCall): void
    {
        if (! $methodCall->name instanceof Name && ! $methodCall->name instanceof Identifier) {
            return;
        }

        if (! $methodCall->class instanceof Name) {
            return;
        }

        $class = ReferenceTypeResolver::resolveClassName($fnScope, $methodCall->class->name);
        if (! $class) {
            return;
        }

        $definition = $this->shallowIndex->getClass($class);
        if (! $definition) {
            return;
        }

        $shallowMethodDefinition = $definition->getMethod($methodCall->name->name);
        if (! $shallowMethodDefinition) {
            return;
        }

        $this->applySideEffectsFromCall(new SideEffectCallEvent(
            definition: $methodDefinition,
            calledDefinition: $shallowMethodDefinition,
            node: $methodCall,
            scope: $fnScope,
            arguments: new ArrayArgumentTypeBag($fnScope->getArgsTypes($methodCall->args)),
        ));
    }

    private function analyzeFuncCall(FunctionLikeDefinition $methodDefinition, Scope $fnScope, FuncCall $call): void
    {
        $name = $call->name->getAttribute('namespacedName', $call->name);
        if (! $name instanceof Name) {
            return;
        }

        $functionDefinition = $this->shallowIndex->getFunction($name);
        if (! $functionDefinition) {
            return;
        }

        $this->applySideEffectsFromCall(new SideEffectCallEvent(
            definition: $methodDefinition,
            calledDefinition: $functionDefinition,
            node: $call,
            scope: $fnScope,
            arguments: new ArrayArgumentTypeBag($fnScope->getArgsTypes($call->args)),
        ));
    }

    private function applySideEffectsFromCall(SideEffectCallEvent $event): void
    {
        foreach ($event->calledDefinition->type->exceptions as $exception) {
            $event->definition->type->exceptions[] = $exception;
        }

        Context::getInstance()->extensionsBroker->afterSideEffectCallAnalyzed($event);
    }

    private function isThisPropertyAssignment(NodeAbstract $n): bool
    {
        return $n instanceof Assign
            && $n->var instanceof PropertyFetch
            && $n->var->var instanceof Variable
            && $n->var->var->name === 'this'
            && $n->var->name instanceof Identifier
            && is_string($n->var->name->name);
    }

    private function isParentConstructCall(NodeAbstract $n): bool
    {
        return $n instanceof StaticCall
            && $n->class instanceof Name
            && $n->class->name === 'parent'
            && $n->name instanceof Identifier
            && $n->name->name === '__construct';
    }

    private function isPotentialSetterCall(NodeAbstract $n): bool
    {
        return $n instanceof MethodCall
            && $n->name instanceof Identifier
            && is_string($n->name->name);
    }

    private function isSelfTypeOrCallOnSelfType(Type $t): bool
    {
        if ($t instanceof SelfType) {
            return true;
        }

        if (! $t instanceof MethodCallReferenceType) {
            return false;
        }

        return $this->isSelfTypeOrCallOnSelfType($t->callee);
    }
}
