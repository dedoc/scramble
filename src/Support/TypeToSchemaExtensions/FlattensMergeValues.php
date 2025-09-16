<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;

trait FlattensMergeValues
{
    /**
     * @param  ArrayItemType_[]  $items
     * @return ArrayItemType_[]
     */
    protected function flattenMergeValues(array $items): array
    {
        return collect($items)
            ->flatMap(function (ArrayItemType_ $item) {
                if ($item->value instanceof KeyedArrayType) {
                    $item->value->items = $this->flattenMergeValues($item->value->items);
                    $item->value->isList = KeyedArrayType::checkIsList($item->value->items);

                    return [$item];
                }

                if ($item->value->isInstanceOf(JsonResource::class)) {
                    $resource = $this->getResourceType($item->value);

                    if ($resource->isInstanceOf(MissingValue::class)) {
                        return [];
                    }

                    if (
                        $resource instanceof Union
                        && (new TypeWalker)->first($resource, fn (Type $t) => $t->isInstanceOf(MissingValue::class))
                    ) {
                        $item->isOptional = true;

                        return [$item];
                    }
                }

                if (
                    $item->value instanceof Union
                    && (new TypeWalker)->first($item->value, $this->isUnionWithMissingValue(...))
                ) {
                    $newType = (new TypeWalker)->replace($item->value, function (Type $t) {
                        if (! $this->isUnionWithMissingValue($t)) {
                            return null;
                        }

                        return Union::wrap(array_values(
                            array_filter($t->types, fn (Type $t) => ! $t->isInstanceOf(MissingValue::class))
                        ));
                    });

                    if ($newType instanceof VoidType) {
                        return [];
                    }

                    $item->isOptional = true;

                    $item->value = $newType;

                    return $this->flattenMergeValues([$item]);
                }

                if (
                    $item->value instanceof Generic
                    && $item->value->isInstanceOf(MergeValue::class)
                ) {
                    $arrayToMerge = $item->value->templateTypes[1];

                    // Second generic argument of the `MergeValue` class must be a keyed array.
                    // Otherwise, we ignore it from the resulting array.
                    if (! $arrayToMerge instanceof KeyedArrayType) {
                        return [];
                    }

                    $arrayToMergeItems = $this->flattenMergeValues($arrayToMerge->items);

                    $mergingArrayValuesShouldBeRequired = $item->value->templateTypes[0] instanceof LiteralBooleanType
                        && $item->value->templateTypes[0]->value === true;

                    if (! $mergingArrayValuesShouldBeRequired || $item->isOptional) {
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
     * @phpstan-assert-if-true Union $type
     */
    private function isUnionWithMissingValue(Type $type): bool
    {
        return $type instanceof Union
            && (bool) array_filter($type->types, fn (Type $t) => $t->isInstanceOf(MissingValue::class));
    }

    /**
     * @todo Maybe does not belong here as simply provides a knowledge about locating a type in a json resource generics.
     * This is something similar to Scramble's PRO wrap handling logic.
     */
    private function getResourceType(Type $type): Type
    {
        if (! $type instanceof Generic) {
            return new UnknownType;
        }

        if ($type->isInstanceOf(AnonymousResourceCollection::class)) {
            return $type->templateTypes[0] ?? new UnknownType;
        }

        if ($type->isInstanceOf(JsonResource::class)) {
            return $type->templateTypes[0];
        }

        return new UnknownType;
    }
}
