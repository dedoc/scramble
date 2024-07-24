<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\InferExtensions\ResourceCollectionTypeInfer;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JsonResourceTypeToSchema extends TypeToSchemaExtension
{
    use FlattensMergeValues;
    use MergesOpenApiObjects;

    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(JsonResource::class)
            && ! $type->isInstanceOf(AnonymousResourceCollection::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        $definition = $this->infer->analyzeClass($type->name);

        $array = ($def = $type->getMethodDefinition('toArray'))
            ? $def->type->getReturnType()
            : new \Dedoc\Scramble\Support\Type\UnknownType;

        if (! $array instanceof KeyedArrayType) {
            if ($type->isInstanceOf(ResourceCollection::class)) {
                $array = (new ResourceCollectionTypeInfer)->getBasicCollectionType($definition);
            } else {
                return new UnknownType;
            }
        }

        // The case when `toArray` is not defined.
        if ($array instanceof ArrayType) {
            return $this->openApiTransformer->transform($array);
        }

        if (! $array instanceof KeyedArrayType) {
            return new UnknownType;
        }

        $array->items = $this->flattenMergeValues($array->items);
        $array->isList = KeyedArrayType::checkIsList($array->items);

        return $this->openApiTransformer->transform($array);
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type)
    {
        $definition = $this->infer->analyzeClass($type->name);

        $additional = $type->templateTypes[1 /* TAdditional */] ?? new UnknownType;

        $openApiType = $this->openApiTransformer->transform($type);

        if (($withArray = $definition->getMethodCallType('with')) instanceof KeyedArrayType) {
            $withArray->items = $this->flattenMergeValues($withArray->items);
        }
        if ($additional instanceof KeyedArrayType) {
            $additional->items = $this->flattenMergeValues($additional->items);
        }

        $shouldWrap = ($wrapKey = $type->name::$wrap ?? null) !== null
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

        return Response::make(200)
            ->description('`'.$this->components->uniqueSchemaName($type->name).'`')
            ->setContent(
                'application/json',
                Schema::fromType($openApiType),
            );
    }

    private function mergeResourceTypeAndAdditionals(string $wrapKey, Reference|OpenApiObjectType $openApiType, ?KeyedArrayType $withArray, ?KeyedArrayType $additional)
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

    public function reference(ObjectType $type)
    {
        return new Reference('schemas', $type->name, $this->components);

        /*
         * @todo: Allow (enforce) user to explicitly pass short and unique names for the reference and avoid passing components.
         * Otherwise, only class names are correctly handled for now.
         */
        return Reference::in('schemas')
            ->shortName(class_basename($type->name))
            ->uniqueName($type->name);
    }
}
