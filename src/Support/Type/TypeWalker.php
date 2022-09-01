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

        if ($subject instanceof Union) {
            $index = array_search($search, $subject->types);
            if ($index !== false) {
                $subject->types[$index] = $replace;
            }
            return TypeHelper::mergeTypes(...$subject->types);
        }

        return $subject;
    }
}
