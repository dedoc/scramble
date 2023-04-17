<?php

namespace Dedoc\Scramble\Support\Type;

class TypeWalker
{
    private array $visitedNodes = [];

    public function find(Type $type, callable $lookup): array
    {
        if (in_array($type, $this->visitedNodes)) {
            return [];
        }
        $this->visitedNodes[] = $type;

        $foundTypes = $lookup($type) ? [$type] : [];

        $children = $type->children();
        foreach ($children as $child) {
            $foundTypes = array_merge($foundTypes, $this->find($child, $lookup));
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

    public function replace(Type $subject, Type $search, Type $replace): Type
    {
        if (in_array($subject, $this->visitedNodes)) {
            return $subject;
        }
        $this->visitedNodes[] = $subject;

        if ($subject === $search) {
            return $replace;
        }

        $propertiesWithNodes = $subject->nodes();
        foreach ($propertiesWithNodes as $propertyWithNode) {
            $node = $subject->$propertyWithNode;
            if (! is_array($node)) {
                $subject->$propertyWithNode = TypeHelper::unpackIfArrayType($this->replace($node, $search, $replace));
            } else {
                foreach ($node as $index => $item) {
                    $subject->$propertyWithNode[$index] = TypeHelper::unpackIfArrayType($this->replace($item, $search, $replace));
                }
            }
        }

        return $subject;
    }
}
