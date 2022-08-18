<?php

namespace Dedoc\ApiDocs\Support\ResponseExtractor;

use Dedoc\ApiDocs\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\ApiDocs\Support\Generator\OpenApi;
use Dedoc\ApiDocs\Support\Generator\Reference;
use Dedoc\ApiDocs\Support\Generator\Response;
use Dedoc\ApiDocs\Support\Generator\Schema;
use Dedoc\ApiDocs\Support\Type\Generic;
use Dedoc\ApiDocs\Support\Type\Identifier;
use Dedoc\ApiDocs\Support\TypeHandlers\PhpDocTypeWalker;
use Dedoc\ApiDocs\Support\TypeHandlers\ResolveFqnPhpDocTypeVisitor;
use Dedoc\ApiDocs\Support\TypeHandlers\TypeHandlers;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

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
        return collect(Arr::wrap($this->getReturnTypes()))
            ->filter()
            ->map(function ($type) {
                $description = $type instanceof Reference
                    ? ComplexTypeHandlers::$components->getSchema($type->fullName)->type->description
                    : $type->description;

                $type->setDescription('');

                return Response::make(200)
                    ->description($description)
                    ->setContent('application/json', Schema::fromType($type));
            })
            ->values()
            ->all();
    }

    private function getReturnTypes()
    {
        if ($this->methodPhpDocNode) {
            if ($returnType = $this->getReturnTypesFromPhpDoc()) {
                return $returnType;
            }
        }

        if ($this->reflectionMethod) {
            if ($returnType = $this->reflectionMethod->getReturnType()) {
                return ComplexTypeHandlers::handle(new Identifier($returnType->getName()));
            }
        }

        if ($codeAnalysisReturnTypes = $this->getReturnTypesFromCode()) {
            return $codeAnalysisReturnTypes;
        }

        return null;
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
            return ComplexTypeHandlers::handle(
                new Identifier($this->classAliasesMap[$jsonResourceReturnNode->expr->class->toString()] ?? $jsonResourceReturnNode->expr->class->toString())
            );
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
            return ComplexTypeHandlers::handle(new Generic(
                new Identifier(AnonymousResourceCollection::class),
                array_map(
                    fn ($name) => new Identifier($name),
                    [$this->classAliasesMap[$anonymousResourceCollection->expr->class->toString()] ?? $anonymousResourceCollection->expr->class->toString()],
                )
            ));
        }

        return null;
    }

    private function getReturnTypesFromPhpDoc()
    {
        $returnTagValue = $this->methodPhpDocNode->getReturnTagValues()[0] ?? null;

        if (! $returnTagValue || ! $returnTagValue->type) {
            return null;
        }

        $getFqn = fn ($className) => $this->classAliasesMap[$className] ?? $className;

        PhpDocTypeWalker::traverse($returnTagValue->type, [new ResolveFqnPhpDocTypeVisitor($getFqn)]);

        return TypeHandlers::handle($returnTagValue->type);
    }
}
