<?php

namespace Dedoc\Scramble\Support\ResponseExtractor;

use Dedoc\Scramble\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Identifier;
use Dedoc\Scramble\Support\TypeHandlers\PhpDocTypeWalker;
use Dedoc\Scramble\Support\TypeHandlers\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Support\TypeHandlers\TypeHandlers;
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

    private $nameResolver;

    private OpenApi $openApi;

    private ?PhpDocNode $methodPhpDocNode;

    public function __construct(OpenApi $openApi, Route $route, ?ClassMethod $methodNode, ?\ReflectionMethod $reflectionMethod, ?PhpDocNode $methodPhpDocNode, callable $nameResolver)
    {
        $this->route = $route;
        $this->methodNode = $methodNode;
        $this->reflectionMethod = $reflectionMethod;
        $this->nameResolver = $nameResolver;
        $this->openApi = $openApi;
        $this->methodPhpDocNode = $methodPhpDocNode;
    }

    public function __invoke()
    {
        return collect(Arr::wrap($this->getReturnTypes()))
            ->filter()
            ->map(function ($type) {
                $hint = $type instanceof Reference
                    ? ComplexTypeHandlers::$components->getSchema($type->fullName)->type->hint
                    : $type->hint;

                $type->setDescription('');

                return Response::make(200)
                    ->description($hint)
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
                && is_a($node->expr->class->toString(), JsonResource::class, true)
        );

        /*
         * We can try to handle the resource if initiated with `new`
         */
        if ($jsonResourceReturnNode) {
            return ComplexTypeHandlers::handle(
                new Identifier($jsonResourceReturnNode->expr->class->toString())
            );
        }

        $anonymousResourceCollection = (new NodeFinder())->findFirst(
            $this->methodNode,
            fn (Node $node) => $node instanceof Node\Stmt\Return_
                && $node->expr instanceof Node\Expr\StaticCall
                && $node->expr->name instanceof Node\Identifier
                && $node->expr->name->toString() === 'collection'
                && $node->expr->class instanceof Node\Name
                && is_a($node->expr->class->toString(), JsonResource::class, true)
        );

        if ($anonymousResourceCollection) {
            return ComplexTypeHandlers::handle(new Generic(
                new Identifier(AnonymousResourceCollection::class),
                array_map(
                    fn ($name) => new Identifier($name),
                    [$anonymousResourceCollection->expr->class->toString()],
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

        PhpDocTypeWalker::traverse($returnTagValue->type, [new ResolveFqnPhpDocTypeVisitor($this->nameResolver)]);

        return TypeHandlers::handle($returnTagValue->type);
    }
}
