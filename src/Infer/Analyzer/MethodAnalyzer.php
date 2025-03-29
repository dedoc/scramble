<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\SideEffectCallEvent;
use Dedoc\Scramble\Infer\Handler\IndexBuildingHandler;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

class MethodAnalyzer
{
    public function __construct(
        private Index $index,
        private ClassDefinition $classDefinition,
    ) {}

    public function analyze(FunctionLikeDefinition $methodDefinition, array $indexBuilders = [], bool $withSideEffects = false)
    {
        $inferer = $this->traverseClassMethod(
            [$node = $this->getClassReflector()->getMethod($methodDefinition->type->name)->getAstNode()],
            $methodDefinition,
            $indexBuilders,
        );

        $methodDefinition = $this->index
            ->getClassDefinition($this->classDefinition->name)
            ->methods[$methodDefinition->type->name];

        if ($withSideEffects) {
            $this->analyzeSideEffects($methodDefinition, $node, $inferer);
        }

        $methodDefinition->isFullyAnalyzed = true;

        return $methodDefinition;
    }

    private function getClassReflector(): ClassReflector
    {
        return ClassReflector::make($this->classDefinition->name);
    }

    private function traverseClassMethod(array $nodes, FunctionLikeDefinition $methodDefinition, array $indexBuilders = []): TypeInferer
    {
        $traverser = new NodeTraverser;

        $nameResolver = new FileNameResolver($this->getClassReflector()->getNameContext());

        $traverser->addVisitor($inferer = new TypeInferer(
            $this->index,
            $nameResolver,
            $scope = new Scope($this->index, new NodeTypesResolver, new ScopeContext($this->classDefinition), $nameResolver),
            Context::getInstance()->extensionsBroker->extensions,
            [new IndexBuildingHandler($indexBuilders)],
        ));

        $node = (new NodeFinder)
            ->findFirst(
                $nodes,
                fn ($n) => $n instanceof ClassMethod && $n->name->toString() === $methodDefinition->type->name
            );

        $traverser->traverse(Arr::wrap($node));

        return $inferer;
    }

    private function analyzeSideEffects(FunctionLikeDefinition $methodDefinition, ClassMethod $node, TypeInferer $inferer): void
    {
        $fnScope = $inferer->getFunctionLikeScope($node);

        if (! $fnScope) {
            return;
        }

        foreach ($inferer->getMethodCalls() as $methodCall) {
            match (true) {
                $methodCall instanceof MethodCall || $methodCall instanceof NullsafeMethodCall => $this->analyzeMethodCall($methodDefinition, $fnScope, $methodCall, $inferer),
                $methodCall instanceof StaticCall => $this->analyzeStaticMethodCall($methodDefinition, $fnScope, $methodCall, $inferer),
                $methodCall instanceof FuncCall => null,
                $methodCall instanceof New_ => null,
                default => null,
            };
        }
    }

    private function analyzeMethodCall(FunctionLikeDefinition $methodDefinition, Scope $fnScope, MethodCall|NullsafeMethodCall $methodCall, TypeInferer $inferer): void
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
        // this part is literally similar with the shallow analysis...

        // get shallow method definition (get shallow callee type, get the shallow definition)
        $calleeType = (new ShallowTypeResolver($this->index, $inferer->nameResolver))->resolve($fnScope->getType($methodCall->var));
        if ($calleeType instanceof TemplateType && $calleeType->is) {
            $calleeType = $calleeType->is;
        }
        if (! $calleeType instanceof ObjectType) {
            return;
        }

        $class = ReferenceTypeResolver::resolveClassName($fnScope, $calleeType->name);
        if (! $class) {
            return;
        }

        $calleeType = clone $calleeType;
        $calleeType->name = $class;

        $definition = $this->index->getClassDefinition($calleeType->name) ?: (new ClassAnalyzer($this->index))->analyze($calleeType->name);
        if (! $definition) {
            return;
        }

        $shallowMethodDefinition = $definition->getMethodDefinitionWithoutAnalysis($methodCall->name->name);
        if (! $shallowMethodDefinition) {
            return;
        }

        // just so analysis happens.
        (new ShallowTypeResolver($this->index, $inferer->nameResolver))->resolve(new MethodCallReferenceType(
            $calleeType,
            $methodCall->name->name,
            $arguments = $fnScope->getArgsTypes($methodCall->args),
        ));

        $this->applySideEffectsFromCall(new SideEffectCallEvent(
            definition: $methodDefinition,
            calledDefinition: $shallowMethodDefinition,
            node: $methodCall,
            scope: $fnScope,
            arguments: $arguments,
        ));
    }

    private function analyzeStaticMethodCall(FunctionLikeDefinition $methodDefinition, Scope $fnScope, StaticCall $methodCall, TypeInferer $inferer): void
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

        $definition = $this->index->getClassDefinition($class) ?: (new ClassAnalyzer($this->index))->analyze($class);
        if (! $definition) {
            return;
        }

        $shallowMethodDefinition = $definition->getMethodDefinitionWithoutAnalysis($methodCall->name->name);
        if (! $shallowMethodDefinition) {
            return;
        }

        // just so analysis happens.
        (new ShallowTypeResolver($this->index, $inferer->nameResolver))->resolve(new StaticMethodCallReferenceType(
            $class,
            $methodCall->name->name,
            $arguments = $fnScope->getArgsTypes($methodCall->args),
        ));

        $this->applySideEffectsFromCall(new SideEffectCallEvent(
            definition: $methodDefinition,
            calledDefinition: $shallowMethodDefinition,
            node: $methodCall,
            scope: $fnScope,
            arguments: $arguments,
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
