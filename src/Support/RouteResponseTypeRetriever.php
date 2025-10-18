<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Infer\Definition\FunctionLikeAstDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Type\AbstractTypeVisitor;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeTraverser;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

class RouteResponseTypeRetriever
{
    public function __construct(private RouteInfo $routeInfo) {}

    public function getResponseType(): ?Type
    {
        if (! $this->routeInfo->getActionType()) {
            return null;
        }

        if ($manuallyDefinedType = $this->getManuallyDefinedType()) {
            return $manuallyDefinedType;
        }

        return $this->getInferredType();
    }

    private function getManuallyDefinedType(): ?Type
    {
        if ($annotatedBodyType = $this->getAnnotatedBodyType()) {
            return $annotatedBodyType;
        }

        if ($phpDocType = $this->getMethodPhpDocReturnType()) {
            return $phpDocType;
        }

        return null;
    }

    private function getAnnotatedBodyType(): ?Type
    {
        $definition = $this->routeInfo->getActionDefinition();

        if (! $definition instanceof FunctionLikeAstDefinition) {
            return null;
        }

        $inferredTypeAttribute = $definition->getInferredReturnType();

        $types = $inferredTypeAttribute instanceof Union
            ? $inferredTypeAttribute->types
            : [$inferredTypeAttribute];

        $someReturnTypeHasBodyAnnotation = collect($types)->some(function (Type $type) {
            /** @var PhpDocNode $docNode */
            if (! $docNode = $type->getAttribute('docNode')) {
                return false;
            }

            return (bool) ($docNode->getVarTagValues()[0]->type ?? null);
        });

        if ($someReturnTypeHasBodyAnnotation) {
            return $inferredTypeAttribute;
        }

        return null;
    }

    private function getInferredType(): ?Type
    {
        if ($this->routeInfo->isClassBased()) {
            if (! $methodType = $this->routeInfo->getActionType()) {
                return null;
            }

            return (new ObjectType($this->routeInfo->className()))
                ->getMethodReturnType($methodType->name);
        }

        if (! $definition = $this->routeInfo->getActionDefinition()) {
            return null;
        }

        return ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new CallableCallReferenceType($definition->type, [])
            );
    }

    private function getMethodPhpDocReturnType(): ?Type
    {
        if (! $phpDocReturnNode = $this->getDocReturnNode()) {
            return null;
        }

        $source = $phpDocReturnNode->getAttribute('source');

        $phpDocReturnType = PhpDocTypeHelper::toType($phpDocReturnNode);

        if ($source === 'response') {
            return $phpDocReturnType;
        }

        /*
         * The code below is used to add a backward compatibility for @return annotation support.
         */
        $inferredReturnType = $this->getInferredType();

        if (! $inferredReturnType || $inferredReturnType instanceof UnknownType) {
            return $phpDocReturnType;
        }

        if (
            $phpDocReturnType instanceof Generic
            && (new TypeWalker)->first($phpDocReturnType, fn (Type $t) => $t->isInstanceOf(JsonResource::class) || $t->isInstanceOf(Model::class))
        ) {
            return $phpDocReturnType;
        }

        $phpDocReturnTypeWeight = $phpDocReturnType ? $this->countKnownTypes($phpDocReturnType) : 0;
        $inferredReturnTypeWeight = $this->countKnownTypes($inferredReturnType);
        if ($phpDocReturnTypeWeight > $inferredReturnTypeWeight) {
            return $phpDocReturnType;
        }

        return null;
    }

    private function getDocReturnNode(): ?TypeNode
    {
        if (! $this->routeInfo->phpDoc()) {
            return null;
        }

        if (($responseType = $this->routeInfo->phpDoc()->getReturnTagValues('@response')[0] ?? null) && optional($responseType)->type) {
            $responseType->type->setAttribute('source', 'response');

            return $responseType->type;
        }

        if (($returnType = $this->routeInfo->phpDoc()->getReturnTagValues()[0] ?? null) && optional($returnType)->type) {
            return $returnType->type;
        }

        return null;
    }

    private function countKnownTypes(Type $type): int
    {
        $counterVisitor = new class extends AbstractTypeVisitor
        {
            public int $count = 0;

            public function leave(Type $type): ?Type
            {
                if (
                    $type instanceof ObjectType
                    || $type instanceof StringType
                    || $type instanceof IntegerType
                    || $type instanceof FloatType
                    || $type instanceof BooleanType
                    || $type instanceof NullType
                    /*
                     * Give some weight for keyed array item so when comparing `array<mixed>` to `array{foo: unknown}`,
                     * the keyed array is preferred.
                     */
                    || $type instanceof ArrayItemType_ && is_string($type->key)
                ) {
                    $this->count++;
                }

                return null;
            }
        };

        (new TypeTraverser([$counterVisitor]))->traverse($type);

        return $counterVisitor->count;
    }
}
