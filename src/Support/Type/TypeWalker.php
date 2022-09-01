<?php

namespace Dedoc\Scramble\Support\Type;

class TypeWalker
{
    public static function find(Type $type, callable $lookup): array
    {
        $foundTypes = $lookup($type) ? [$type] : [];

        foreach ($type->children() as $child) {
            $foundTypes = array_merge($foundTypes, static::find($child, $lookup));
        }

        return $foundTypes;
    }

    public static function replace(Type $subject, Type $search, Type $replace): Type
    {
        if ($subject === $search) {
            return $replace;
        }

        $propertiesWithNodes = $subject->nodes();
        foreach ($propertiesWithNodes as $propertyWithNode) {
            $node = $subject->$propertyWithNode;
            if (! is_array($node)) {
                $subject->$propertyWithNode = static::replace($node, $search, $replace);
            } else {
                foreach ($node as $index => $item) {
                    $subject->$propertyWithNode[$index] = static::replace($item, $search, $replace);
                }
            }
        }

        return $subject;
    }
}
