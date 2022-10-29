<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Dedoc\Scramble\Support\InferExtensions\ResourceCollectionTypeInfer;

class JsonResourceTypeToSchema extends TypeToSchemaExtension
{
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
        $type = $this->infer->analyzeClass($type->name);

        $array = $type->getMethodCallType('toArray');

        if (! $array instanceof ArrayType) {
            if ($type->isInstanceOf(ResourceCollection::class)) {
                $array = (new ResourceCollectionTypeInfer)->getBasicCollectionType($type);
            } else {
                return new UnknownType();
            }
        }

        $array->items = $this->flattenMergeValues($array->items);

        return $this->openApiTransformer->transform($array);
    }

    private function flattenMergeValues(array $items)
    {
        return collect($items)
            ->flatMap(function (ArrayItemType_ $item) {
                if ($item->value instanceof ArrayType) {
                    $item->value->items = $this->flattenMergeValues($item->value->items);

                    return [$item];
                }

                if (
                    $item->value instanceof Generic
                    && $item->value->isInstanceOf(MergeValue::class)
                ) {
                    $arrayToMerge = $item->value->genericTypes[1];

                    // Second generic argument of the `MergeValue` class must be an array.
                    // Otherwise, we ignore it from the resulting array.
                    if (! $arrayToMerge instanceof ArrayType) {
                        return [];
                    }

                    $arrayToMergeItems = $this->flattenMergeValues($arrayToMerge->items);

                    $mergingArrayValuesShouldBeRequired = $item->value->genericTypes[0] instanceof LiteralBooleanType
                        && $item->value->genericTypes[0]->value === true;

                    if (! $mergingArrayValuesShouldBeRequired) {
                        foreach ($arrayToMergeItems as $mergingItem) {
                            $mergingItem->isOptional = true;
                        }
                    }

                    return $arrayToMergeItems;
                }

                return [$item];
            })
            ->values()
            ->all();
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type)
    {
        $additional = $type->getPropertyFetchType('additional');

        $type = $this->infer->analyzeClass($className = $type->name);

        $withArray = $type->getMethodCallType('with');
        if ($withArray instanceof ArrayType) {
            $withArray->items = $this->flattenMergeValues($withArray->items);
        }
        if ($additional instanceof ArrayType) {
            $additional->items = $this->flattenMergeValues($additional->items);
        }

        $wrapKey = $type->name::$wrap ?? null;

        $shouldWrap = $withArray instanceof ArrayType || $additional instanceof ArrayType || $wrapKey !== null;

        $wrapKey = $wrapKey ?: 'data';

        $openApiType = $this->openApiTransformer->transform($type);
        if ($shouldWrap) {
            $openApiType = (new \Dedoc\Scramble\Support\Generator\Types\ObjectType())
                ->addProperty($wrapKey, $openApiType)
                ->setRequired([$wrapKey]);

            if ($withArray instanceof ArrayType) {
                $withType = $this->openApiTransformer->transform($withArray);

                foreach ($withType->properties as $name => $property) {
                    $openApiType->addProperty($name, $property);
                }

                $openApiType->addRequired(array_keys($withType->properties));
            }

            if ($additional instanceof ArrayType) {
                $additionalType = $this->openApiTransformer->transform($additional);

                foreach ($additionalType->properties as $name => $property) {
                    $openApiType->addProperty($name, $property);
                }

                $openApiType->addRequired(array_keys($additionalType->properties));
            }
        }

        return Response::make(200)
            ->description('`'.$this->components->uniqueSchemaName($className).'`')
            ->setContent(
                'application/json',
                Schema::fromType($openApiType),
            );
    }

    public function reference(ObjectType $type)
    {
        return new Reference('schemas', $type->name, $this->components);

        /*
         * @todo: Allow (enforce) user to explicitly pass short and unique names for the reference.
         * Otherwise, only class names are correctly handled for now.
         */
        return Reference::in('schemas')
            ->shortName(class_basename($type->name))
            ->uniqueName($type->name);
    }
}
