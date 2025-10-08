<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\SideEffectCallEvent;
use Dedoc\Scramble\Infer\Handler\IndexBuildingHandler;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Infer\UnresolvableArgumentTypeBag;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Arr;
use LogicException;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;

class FunctionLikeAstDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    private LazyShallowReflectionIndex $shallowIndex;

    /**
     * @param  IndexBuilder<array<string, mixed>>[]  $indexBuilders
     */
    public function __construct(
        public string $name,
        public FunctionLike $functionLike,
        public Index $index,
        public FileNameResolver $fileNameResolver,
        public ?ClassDefinition $classDefinition = null,
        public array $indexBuilders = [],
        public bool $withSideEffects = false,
    ) {
        $this->shallowIndex = app(LazyShallowReflectionIndex::class);
    }

    public function build(): FunctionLikeDefinition
    {
        $inferrer = $this->traverseAstNode($this->functionLike);

        $scope = $inferrer->getFunctionLikeScope($this->functionLike);

        if (! $definition = $scope?->context->functionDefinition) {
            throw new LogicException('No definition in scope found.');
        }

        if ($this->functionLike instanceof ClassMethod && $scope) { // @phpstan-ignore booleanAnd.rightAlwaysTrue
            $definition->selfOutTypeBuilder = new SelfOutTypeBuilder($scope, $this->functionLike);
        }

        $this->overrideInferredReturnTypeWithManualAnnotation($definition);

        if ($this->withSideEffects) {
            $this->analyzeSideEffects($definition, $inferrer);
        }

        $definition->isFullyAnalyzed = true;

        return $definition;
    }

    private function traverseAstNode(Node $node): TypeInferer
    {
        $traverser = new NodeTraverser;

        $traverser->addVisitor($inferrer = new TypeInferer(
            $this->index,
            $this->fileNameResolver,
            new Scope($this->index, new NodeTypesResolver, new ScopeContext($this->classDefinition), $this->fileNameResolver),
            Context::getInstance()->extensionsBroker->extensions,
            [new IndexBuildingHandler($this->indexBuilders)],
        ));

        $traverser->traverse(Arr::wrap($node));

        return $inferrer;
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
        if (! $methodCall->name instanceof Identifier) {
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
            arguments: new UnresolvableArgumentTypeBag($fnScope->getArgsTypes($methodCall->args)),
        ));
    }

    private function analyzeStaticMethodCall(FunctionLikeDefinition $methodDefinition, Scope $fnScope, StaticCall $methodCall): void
    {
        if (! $methodCall->name instanceof Identifier) {
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
            arguments: new UnresolvableArgumentTypeBag($fnScope->getArgsTypes($methodCall->args)),
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
            arguments: new UnresolvableArgumentTypeBag($fnScope->getArgsTypes($call->args)),
        ));
    }

    private function applySideEffectsFromCall(SideEffectCallEvent $event): void
    {
        foreach ($event->calledDefinition->type->exceptions as $exception) {
            $event->definition->type->exceptions[] = $exception;
        }

        Context::getInstance()->extensionsBroker->afterSideEffectCallAnalyzed($event);
    }

    private function overrideInferredReturnTypeWithManualAnnotation(FunctionLikeDefinition $definition): void
    {
        $phpDoc = $this->functionLike->getAttribute('parsedPhpDoc');
        if (! $phpDoc instanceof PhpDocNode) {
            return;
        }

        /** @var ReturnTagValueNode|null $scrambleReturn */
        if (! $scrambleReturn = Arr::first($phpDoc->getReturnTagValues('@scramble-return'))) {
            return;
        }

        $type = PhpDocTypeHelper::toType($scrambleReturn->type);
        foreach (($this->classDefinition?->templateTypes ?: []) as $template) {
            $type = (new TypeWalker)->map($type, fn ($t) => $t instanceof ObjectType && $t->name === $template->name ? $template : $t);
        }

        $definition->type->setReturnType($type);
    }

    public static function resolveFunctionExceptions(Scope $scope, FunctionLikeDefinition $functionLikeDefinition): void
    {
        $functionType = $functionLikeDefinition->type;

        foreach ($functionType->exceptions as $i => $exceptionType) {
            $exception = (new ReferenceTypeResolver($scope->index))->resolve($scope, $exceptionType);
            if (! $exception instanceof ObjectType) {
                continue;
            }
            $functionType->exceptions[$i] = $exception;
        }
    }

    public static function resolveFunctionReturnReferences(Scope $scope, FunctionLikeDefinition $functionLikeDefinition): void
    {
        $functionType = $functionLikeDefinition->type;

        $returnType = $functionType->getReturnType();
        $resolvedReference = ReferenceTypeResolver::getInstance()->resolve($scope, $returnType);
        $functionType->setReturnType($resolvedReference);

        $annotatedReturnType = $functionType->getAttribute('annotatedReturnType');

        if ($annotatedReturnType instanceof Type) {
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
                $isAnnotatedAsArray = $annotatedReturnType instanceof ArrayType
                    || $annotatedReturnType instanceof KeyedArrayType;

                if ($isAnnotatedAsArray && $t instanceof LateResolvingType) {
                    return true;
                }

                if ($t instanceof TemplateType && ! $t->is) {
                    return true;
                }

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
}
