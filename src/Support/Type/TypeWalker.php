<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Services\RecursionGuard;

class TypeWalker
{
    private array $visitedNodes = [];

    public function first(Type $type, callable $lookup): ?Type
    {
        return RecursionGuard::run($type, function () use ($type, $lookup) {
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
        }, fn () => null);
    }

    public function replace(Type $subject, callable $replacer): Type
    {
        if ($replaced = $replacer($subject)) {
            return $replaced;
        }

        if (in_array($subject, $this->visitedNodes)) {
            return $subject;
        }
        $this->visitedNodes[] = $subject;

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
