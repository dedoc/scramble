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
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Illuminate\Support\Arr;
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
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

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

        $scope = $inferrer->getFunctionLikeScope($this->functionLike);

        if ($this->functionLike instanceof ClassMethod && $scope) {
            $definition->selfOutTypeBuilder = new SelfOutTypeBuilder($scope, $this->functionLike);
        }

        if ($this->withSideEffects) {
            $this->analyzeSideEffects($definition, $inferrer);
        }

        $definition->isFullyAnalyzed = true;

        return $definition;
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
}
