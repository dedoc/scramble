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
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Support\TimeTracker;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
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
            TimeTracker::time('analyzeSideEffects');
            $this->analyzeSideEffects($methodDefinition, $node, $inferer);
            TimeTracker::timeEnd('analyzeSideEffects');
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

        foreach ($inferer->getMethodCalls() as $methodCall) {
            if ($methodCall instanceof MethodCall || $methodCall instanceof NullsafeMethodCall) {
                if (!$methodCall->name instanceof Name && !$methodCall->name instanceof Identifier) {
                    continue;
                }

//                info('side effect call analyzing...', [
//                    $methodCall->name->name,
//                ]);

                // get shallow method definition (get shallow callee type, get the shallow definition)
                $calleeType = (new ShallowTypeResolver($this->index, $inferer->nameResolver))->resolve($fnScope->getType($methodCall->var));
                if ($calleeType instanceof TemplateType && $calleeType->is) {
                    $calleeType = $calleeType->is;
                }
                if (! $calleeType instanceof ObjectType) {
                    continue;
                }

                $definition = $this->index->getClassDefinition($calleeType->name) ?: (new ClassAnalyzer($this->index))->analyze($calleeType->name);
                if (! $definition) {
                    continue;
                }

                $shallowMethodDefinition = $definition->getMethodDefinitionWithoutAnalysis($methodCall->name->name);
                if (! $shallowMethodDefinition) {
                    continue;
                }

                // just so analysis happens.
                (new ShallowTypeResolver($this->index, $inferer->nameResolver))->resolve(new MethodCallReferenceType(
                    $calleeType,
                    $methodCall->name->name,
                    $arguments = $fnScope->getArgsTypes($methodCall->args),
                ));

                foreach ($shallowMethodDefinition->type->exceptions as $exception) {
                    $methodDefinition->type->exceptions[] = $exception;
                }

//                info('side effect call analyzed', [
//                    $calleeType->name,
//                    $methodCall->name->name,
//                    $_result->toString(),
//                ]);

                Context::getInstance()->extensionsBroker->afterSideEffectCallAnalyzed(new SideEffectCallEvent(
                    definition: $methodDefinition,
                    calledDefinition: $shallowMethodDefinition,
                    node: $methodCall,
                    scope: $fnScope,
                    arguments: $arguments,
                ));
            }
            elseif ($methodCall instanceof StaticCall) {}
            elseif ($methodCall instanceof FuncCall) {}
            elseif ($methodCall instanceof New_) {}
        }
    }
}
