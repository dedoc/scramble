<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer\Analyzer\MethodQuery;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceResponse;

class ResourceResponseTypeToSchema extends TypeToSchemaExtension
{
    use FlattensMergeValues;
    use MergesOpenApiObjects;

    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->isInstanceOf(ResourceResponse::class)
            && count($type->templateTypes) >= 1;
    }

    public function toResponse(Type $type)
    {
        $resourceType = $type->templateTypes[0];
        $openApiType = $this->openApiTransformer->transform($resourceType);

        $definition = $this->infer->analyzeClass($resourceType->name);

        $withArray = $definition->getMethodCallType('with');
        $additional = $resourceType instanceof Generic ? ($resourceType->templateTypes[1] ?? null) : null;

        if ($withArray instanceof KeyedArrayType) {
            $withArray->items = $this->flattenMergeValues($withArray->items);
        }
        if ($additional instanceof KeyedArrayType) {
            $additional->items = $this->flattenMergeValues($additional->items);
        }

        $shouldWrap = ($wrapKey = $resourceType->name::$wrap ?? null) !== null
            || $withArray instanceof KeyedArrayType
            || $additional instanceof KeyedArrayType;
        $wrapKey = $wrapKey ?: 'data';

        if ($shouldWrap) {
            $openApiType = $this->mergeResourceTypeAndAdditionals(
                $wrapKey,
                $openApiType,
                $this->normalizeKeyedArrayType($withArray),
                $this->normalizeKeyedArrayType($additional),
            );
        }

        $response = $this->openApiTransformer->toResponse($this->makeBaseResponse($resourceType));

        return $response
            ->description('`'.$this->components->uniqueSchemaName($resourceType->name).'`')
            ->setContent(
                'application/json',
                Schema::fromType($openApiType),
            );
    }

    private function makeBaseResponse(Type $type)
    {
        $definition = $this->infer->analyzeClass($type->name);

        $responseType = new Generic(JsonResponse::class, [new UnknownType, new LiteralIntegerType(200), new KeyedArrayType]);

        $methodQuery = MethodQuery::make($this->infer)
            ->withArgumentType([null, 1], $responseType)
            ->from($definition, 'withResponse');

        $effectTypes = $methodQuery->getTypes(fn ($t) => (bool) (new TypeWalker)->first($t, fn ($t) => $t === $responseType));

        $effectTypes
            ->filter(fn ($t) => $t instanceof AbstractReferenceType)
            ->each(function (AbstractReferenceType $t) use ($methodQuery) {
                ReferenceTypeResolver::getInstance()->resolve($methodQuery->getScope(), $t);
            });

        return $responseType;
    }

    private function mergeResourceTypeAndAdditionals(string $wrapKey, $openApiType, ?KeyedArrayType $withArray, ?KeyedArrayType $additional)
    {
        $resolvedOpenApiType = $openApiType instanceof Reference ? $openApiType->resolve() : $openApiType;
        $resolvedOpenApiType = $resolvedOpenApiType instanceof Schema ? $resolvedOpenApiType->type : $resolvedOpenApiType;

        // If resolved type already contains wrapKey, we don't need to wrap it again. But we still need to merge additionals.
        if ($resolvedOpenApiType instanceof OpenApiObjectType && $resolvedOpenApiType->hasProperty($wrapKey)) {
            $items = array_values(array_filter([
                $openApiType,
                $this->transformNullableType($withArray),
                $this->transformNullableType($additional),
            ]));

            return count($items) > 1 ? (new AllOf)->setItems($items) : $items[0];
        }

        $openApiType = (new OpenApiObjectType)
            ->addProperty($wrapKey, $openApiType)
            ->setRequired([$wrapKey]);

        if ($withArray) {
            $this->mergeOpenApiObjects($openApiType, $this->openApiTransformer->transform($withArray));
        }

        if ($additional) {
            $this->mergeOpenApiObjects($openApiType, $this->openApiTransformer->transform($additional));
        }

        return $openApiType;
    }

    private function normalizeKeyedArrayType($type): ?KeyedArrayType
    {
        return $type instanceof KeyedArrayType ? $type : null;
    }

    private function transformNullableType(?KeyedArrayType $type)
    {
        return $type ? $this->openApiTransformer->transform($type) : null;
    }
}
