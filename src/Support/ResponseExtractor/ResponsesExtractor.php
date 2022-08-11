<?php

namespace Dedoc\Documentor\Support\ResponseExtractor;

use Dedoc\Documentor\Support\Generator\OpenApi;
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
    private OpenApi $openApi;

    public function __construct(OpenApi $openApi, Route $route, ?ClassMethod $methodNode, ?\ReflectionMethod $reflectionMethod, array $classAliasesMap)
    {
        $this->route = $route;
        $this->methodNode = $methodNode;
        $this->reflectionMethod = $reflectionMethod;
        $this->classAliasesMap = $classAliasesMap;
        $this->openApi = $openApi;
    }

    public function __invoke()
    {
        $types = $this->getReturnTypes();

        return array_filter(array_map(function (string $type) {
            if (is_a($type, JsonResource::class, true)) {
                return (new JsonResourceResponseExtractor($this->openApi, $type))->extract();
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
        $jsonResourceReturnNode = (new NodeFinder())->findFirst(
            $this->methodNode,
            fn (Node $node) => $node instanceof Node\Stmt\Return_
                && $node->expr instanceof Node\Expr\New_
                && $node->expr->class instanceof Node\Name
                && is_a($this->classAliasesMap[$node->expr->class->toString()] ?? $node->expr->class->toString(), JsonResource::class, true)
        );

        /*
         * We can try to handle the resource if initiated with `new`
         */
        if ($jsonResourceReturnNode) {
            return [
                $this->classAliasesMap[$jsonResourceReturnNode->expr->class->toString()] ?? $jsonResourceReturnNode->expr->class->toString()
            ];
        }

        return null;
    }
}
