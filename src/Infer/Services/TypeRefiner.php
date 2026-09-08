<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;

class TypeRefiner
{
    public function refine(Type $declared, Type $inferred): Type
    {
        if ($declared instanceof Union) {
            return $this->refineUnion($declared, $inferred);
        }

        if ($this->areSameNominalObject($declared, $inferred)) {
            /** @var ObjectType $declared */
            /** @var ObjectType $inferred */
            return $this->refineObject($declared, $inferred);
        }

        if ($declared instanceof ArrayType || $declared instanceof KeyedArrayType) {
            return $this->refineArray($declared, $inferred);
        }

        return $declared->clone();
    }

    private function refineUnion(Union $declared, Type $inferred): Type
    {
        $inferredMembers = $inferred instanceof Union ? $inferred->types : [$inferred];

        $result = $declared->clone();
        $result->types = array_map(function (Type $declaredMember) use ($inferredMembers) {
            $inferredMember = $this->findCompatibleMember($declaredMember, $inferredMembers);

            return $inferredMember
                ? $this->refine($declaredMember, $inferredMember)
                : $declaredMember->clone();
        }, $declared->types);

        return $result;
    }

    /**
     * @param  Type[]  $inferredMembers
     */
    private function findCompatibleMember(Type $declared, array $inferredMembers): ?Type
    {
        foreach ($inferredMembers as $inferred) {
            if ($this->areSameNominalObject($declared, $inferred)) {
                return $inferred;
            }
        }

        foreach ($inferredMembers as $inferred) {
            if ($this->areCompatibleContainers($declared, $inferred) || $declared->accepts($inferred)) {
                return $inferred;
            }
        }

        return null;
    }

    private function areSameNominalObject(Type $declared, Type $inferred): bool
    {
        return $declared instanceof ObjectType
            && $inferred instanceof ObjectType
            && $declared->name === $inferred->name;
    }

    private function refineObject(ObjectType $declared, ObjectType $inferred): ObjectType
    {
        if (! $declared instanceof Generic && $inferred instanceof Generic) {
            $result = $inferred->clone();
            $result->propertyTypes = $declared->clone()->propertyTypes;
        } else {
            $result = $declared->clone();
        }

        if ($result instanceof Generic && $inferred instanceof Generic) {
            foreach ($inferred->templateTypes as $index => $inferredTemplate) {
                if (! isset($result->templateTypes[$index]) || $result->templateTypes[$index] instanceof UnknownType) {
                    $result->templateTypes[$index] = $inferredTemplate->clone();

                    continue;
                }

                $result->templateTypes[$index] = $this->refine($result->templateTypes[$index], $inferredTemplate);
            }
        }

        return $this->enrichAttributes($result, $declared, $inferred);
    }

    private function refineArray(ArrayType|KeyedArrayType $declared, Type $inferred): Type
    {
        if ($declared instanceof ArrayType && $inferred instanceof ArrayType) {
            $result = $declared->clone();
            $result->key = $this->refineContainerPart($declared->key, $inferred->key);
            $result->value = $this->refineContainerPart($declared->value, $inferred->value);

            return $result;
        }

        if ($declared instanceof ArrayType && $inferred instanceof KeyedArrayType) {
            if (! $declared->key->accepts($inferred->getKeyType())) {
                return $declared->clone();
            }

            $result = $inferred->clone();
            foreach ($result->items as $index => $item) {
                $item->value = $this->refineContainerPart($declared->value, $inferred->items[$index]->value);
                $item->keyType = $inferred->items[$index]->keyType?->clone();
            }

            return $this->enrichAttributes($result, $declared, $inferred);
        }

        if ($declared instanceof KeyedArrayType && $inferred instanceof KeyedArrayType) {
            $result = $declared->clone();

            foreach ($result->items as $index => $declaredItem) {
                $declaredItem->keyType = $declared->items[$index]->keyType?->clone();
                $inferredItem = $this->findArrayItem($declared, $inferred, $index);
                if (! $inferredItem) {
                    continue;
                }

                $declaredItem->value = $this->refineContainerPart($declaredItem->value, $inferredItem->value);
                if ($declaredItem->keyType === null && $inferredItem->keyType !== null) {
                    $declaredItem->keyType = $inferredItem->keyType->clone();
                }
                $this->enrichAttributes($declaredItem, $declared->items[$index], $inferredItem);
            }

            return $result;
        }

        if ($declared instanceof KeyedArrayType && $inferred instanceof ArrayType) {
            $result = $declared->clone();
            foreach ($result->items as $index => $item) {
                $item->value = $this->refineContainerPart($item->value, $inferred->value);
                $item->keyType = $declared->items[$index]->keyType?->clone();
            }

            return $result;
        }

        return $declared->clone();
    }

    private function refineContainerPart(Type $declared, Type $inferred): Type
    {
        if ($declared instanceof MixedType || $declared instanceof UnknownType) {
            return $inferred->clone();
        }

        return $this->refine($declared, $inferred);
    }

    private function findArrayItem(KeyedArrayType $declared, KeyedArrayType $inferred, int $index): ?ArrayItemType_
    {
        if ($declared->isList && $inferred->isList) {
            return $inferred->items[$index] ?? null;
        }

        foreach ($inferred->items as $item) {
            if ($item->key === $declared->items[$index]->key) {
                return $item;
            }
        }

        return null;
    }

    private function areCompatibleContainers(Type $declared, Type $inferred): bool
    {
        return ($declared instanceof ArrayType || $declared instanceof KeyedArrayType)
            && ($inferred instanceof ArrayType || $inferred instanceof KeyedArrayType);
    }

    /**
     * Adds inferred attributes while keeping declared attributes authoritative.
     *
     * @template T of Type
     *
     * @param  T  $result
     * @return T
     */
    private function enrichAttributes(Type $result, Type $declared, Type $inferred): Type
    {
        return $result
            ->mergeAttributes($inferred->attributes())
            ->mergeAttributes($declared->attributes());
    }
}
