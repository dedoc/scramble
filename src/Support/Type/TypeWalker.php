<?php

namespace Dedoc\Scramble\Support\Type;

class TypeWalker
{
    public function find(Type $type, callable $lookup): array
    {
        if ($lookup($type)) {
            return [$type];
        }

        $foundTypes = [];

        $publicChildren = collect($type->nodes())
            ->flatMap(fn ($node) => is_array($type->$node) ? array_values($type->$node) : [$type->$node]);

        foreach ($publicChildren as $child) {
            if ($foundTypesInChildren = $this->find($child, $lookup)) {
                $foundTypes = array_merge($foundTypes, $foundTypesInChildren);
            }
        }

        return $foundTypes;
    }

    public function first(Type $type, callable $lookup): ?Type
    {
        if ($lookup($type)) {
            return $type;
        }

        $publicChildren = collect($type->nodes())
            ->flatMap(fn ($node) => is_array($type->$node) ? array_values($type->$node) : [$type->$node]);

        foreach ($publicChildren as $child) {
            if ($foundType = $this->first($child, $lookup)) {
                return $foundType;
            }
        }

        return null;
    }

    public function replace(Type $subject, callable $replacer): Type
    {
        if ($replaced = $replacer($subject)) {
            return $replaced;
        }

        $propertiesWithNodes = $subject->nodes();

        foreach ($propertiesWithNodes as $propertyWithNode) {
            $node = $subject->$propertyWithNode;
            if (! is_array($node)) {
                $subject->$propertyWithNode = TypeHelper::unpackIfArrayType($this->replace($node, $replacer));
            } else {
                foreach ($node as $index => $item) {
                    $subject->$propertyWithNode[$index] = TypeHelper::unpackIfArrayType($this->replace($item, $replacer));
                }
            }
        }

        return $subject;
    }
}
