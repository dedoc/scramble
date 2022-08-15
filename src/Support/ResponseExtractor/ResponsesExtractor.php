<?php

namespace Dedoc\Documentor\Support\ResponseExtractor;

use Dedoc\Documentor\Support\Generator\OpenApi;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

class ResponsesExtractor
{
    private Route $route;

    private ?ClassMethod $methodNode;

    private ?\ReflectionMethod $reflectionMethod;

    private array $classAliasesMap;

    private OpenApi $openApi;

    private ?PhpDocNode $methodPhpDocNode;

    public function __construct(OpenApi $openApi, Route $route, ?ClassMethod $methodNode, ?\ReflectionMethod $reflectionMethod, ?PhpDocNode $methodPhpDocNode, array $classAliasesMap)
    {
        $this->route = $route;
        $this->methodNode = $methodNode;
        $this->reflectionMethod = $reflectionMethod;
        $this->classAliasesMap = $classAliasesMap;
        $this->openApi = $openApi;
        $this->methodPhpDocNode = $methodPhpDocNode;
    }

    public function __invoke()
    {
        return collect($this->getReturnTypes())
            ->map(function ($type) {
                $type = Arr::wrap($type);

                if (is_a($type[0], AnonymousResourceCollection::class, true) && isset($type[1])) {
                    return (new AnonymousResourceCollectionResponseExtractor($this->openApi, $type[1][0]))->extract();
                }

                if (is_a($type[0], JsonResource::class, true)) {
                    return (new JsonResourceResponseExtractor($this->openApi, $type[0]))->extract();
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function getReturnTypes()
    {
        $types = [];

        if ($codeAnalysisReturnTypes = $this->getReturnTypesFromCode()) {
            return $codeAnalysisReturnTypes;
        }

        if ($this->reflectionMethod) {
            if ($returnType = $this->reflectionMethod->getReturnType()) {
                $types[] = $returnType;
            }
        }

        if ($this->methodPhpDocNode) {
            if ($returnType = $this->getReturnTypesFromPhpDoc()) {
                $types[] = $returnType;
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
                $this->classAliasesMap[$jsonResourceReturnNode->expr->class->toString()] ?? $jsonResourceReturnNode->expr->class->toString(),
            ];
        }

        $anonymousResourceCollection = (new NodeFinder())->findFirst(
            $this->methodNode,
            fn (Node $node) => $node instanceof Node\Stmt\Return_
                && $node->expr instanceof Node\Expr\StaticCall
                && $node->expr->name instanceof Node\Identifier
                && $node->expr->name->toString() === 'collection'
                && $node->expr->class instanceof Node\Name
                && is_a($this->classAliasesMap[$node->expr->class->toString()] ?? $node->expr->class->toString(), JsonResource::class, true)
        );

        if ($anonymousResourceCollection) {
            return [
                [
                    AnonymousResourceCollection::class,
                    [ $this->classAliasesMap[$anonymousResourceCollection->expr->class->toString()] ?? $anonymousResourceCollection->expr->class->toString() ]
                ],
            ];
        }

        return null;
    }

    private function getReturnTypesFromPhpDoc()
    {
        $returnTagValue = $this->methodPhpDocNode->getReturnTagValues()[0] ?? null;

        if (! $returnTagValue) {
            return null;
        }

        $getFqn = fn ($className) => $this->classAliasesMap[$className] ?? $className;

        if ($returnTagValue->type instanceof IdentifierTypeNode) {
            return [
                $getFqn($returnTagValue->type->name),
            ];
        }

        if (
            $returnTagValue->type instanceof GenericTypeNode
            && collect($returnTagValue->type->genericTypes)->every(fn ($gt) => $gt instanceof IdentifierTypeNode)
        ) {
            return [
                $getFqn($returnTagValue->type->type->name),
                array_map(
                    fn ($genericType) => $getFqn($genericType->name),
                    $returnTagValue->type->genericTypes,
                ),
            ];
        }
    }
}
