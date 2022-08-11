<?php

namespace Dedoc\Documentor\Support\ResponseExtractor;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

class ResponsesExtractor
{
    private Route $route;

    private ?ClassMethod $methodNode;

    private ?\ReflectionMethod $reflectionMethod;

    private array $classAliasesMap;

    public function __construct(Route $route, ?ClassMethod $methodNode, ?\ReflectionMethod $reflectionMethod, array $classAliasesMap)
    {
        $this->route = $route;
        $this->methodNode = $methodNode;
        $this->reflectionMethod = $reflectionMethod;
        $this->classAliasesMap = $classAliasesMap;
    }

    public function __invoke()
    {
        $types = $this->getReturnTypes();

        return array_filter(array_map(function (string $type) {
            if (is_a($type, JsonResource::class, true)) {
                return (new JsonResourceResponseExtractor($type))->extract();
            }

            return null;
        }, $types));
    }

    private function getReturnTypes()
    {
        $types = [];

        if ($codeAnalysisReturnTypes = $this->getReturnTypesFromCode()) {
            return $codeAnalysisReturnTypes;
        }

        if ($this->reflectionMethod) {
            if ($returnType = $this->reflectionMethod->getReturnType()) {
                $types[] = (string) $returnType;
            }
        }

        return $types;
    }

    private function getReturnTypesFromCode()
    {
        if (! $this->methodNode) {
            return null;
        }

        // @todo Find all return nodes

        /** @var Node\Stmt\Return_|null $returnNode */
        $returnNode = (new NodeFinder())->findFirst(
            $this->methodNode,
            fn (Node $node) => $node instanceof Node\Stmt\Return_
        );

        /*
         * We can try to handle the resource if initiated with `new`
         */
        if (
            $returnNode
            && $returnNode->expr instanceof Node\Expr\New_
            && $returnNode->expr->class instanceof Node\Name
            && is_a($fqn = $this->classAliasesMap[$returnNode->expr->class->toString()] ?? $returnNode->expr->class->toString(), JsonResource::class, true)
        ) {
            return [$fqn];
        }

        return null;
    }
}
