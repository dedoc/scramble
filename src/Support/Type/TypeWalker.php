<?php

namespace Dedoc\Scramble\Support\Type;

class TypeWalker
{
    private array $visitedNodes = [];

    public function find(Type $type, callable $lookup, ?callable $shouldEnter = null): array
    {
        $shouldEnter = $shouldEnter ?: fn ($t) => true;

        if (! $shouldEnter($type)) {
            return [];
        }

        if (in_array($type, $this->visitedNodes)) {
            return [];
        }
        $this->visitedNodes[] = $type;

        $foundTypes = $lookup($type) ? [$type] : [];

        $children = $type->children();
        foreach ($children as $child) {
            $foundTypes = array_merge($foundTypes, $this->find($child, $lookup, $shouldEnter));
        }

        return $foundTypes;
    }

    public function first(Type $type, callable $lookup): ?Type
    {
        if (in_array($type, $this->visitedNodes)) {
            return null;
        }
        $this->visitedNodes[] = $type;

        if ($lookup($type)) {
            return $type;
        }

        $children = $type->children();
        foreach ($children as $child) {
            if ($foundType = $this->first($child, $lookup)) {
                return $foundType;
            }
        }

        return null;
    }

    public function firstPublic(Type $type, callable $lookup): ?Type
    {
        if ($lookup($type)) {
            return $type;
        }

        $publicChildren = collect($type->publicNodes())
            ->flatMap(fn ($node) => is_array($type->$node) ? array_values($type->$node) : [$type->$node]);

        foreach ($publicChildren as $child) {
            if ($foundType = $this->firstPublic($child, $lookup)) {
                return $foundType;
            }
        }

        return null;
    }

    public function replacePublic(Type $subject, callable $replacer): Type
    {
        if ($replaced = $replacer($subject)) {
            return $replaced;
        }

        $propertiesWithNodes = $subject->publicNodes();

        foreach ($propertiesWithNodes as $propertyWithNode) {
            $node = $subject->$propertyWithNode;
            if (! is_array($node)) {
                $subject->$propertyWithNode = TypeHelper::unpackIfArrayType($this->replacePublic($node, $replacer));
            } else {
                foreach ($node as $index => $item) {
                    $subject->$propertyWithNode[$index] = TypeHelper::unpackIfArrayType($this->replacePublic($item, $replacer));
                }
            }
        }

        return $subject;
    }
}
