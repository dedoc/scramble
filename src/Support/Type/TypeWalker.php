<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Services\RecursionGuard;
use WeakMap;

class TypeWalker
{
    private array $visitedNodes = [];

    private WeakMap $visitedNodesWeakMap;

    public function __construct()
    {
        $this->visitedNodesWeakMap = new WeakMap;
    }

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
                $subject->$propertyWithNode = TypeHelper::unpackIfArray($this->replace($node, $replacer));
            } else {
                foreach ($node as $index => $item) {
                    $subject->$propertyWithNode[$index] = TypeHelper::unpackIfArray($this->replace($item, $replacer));
                }
            }
        }

        return $subject;
    }

    /**
     * Maps a type to a new type. This method is not mutating passed type, hence
     * the callback mush always return a type.
     *
     * @param  callable(Type): Type  $cb
     * @param  (callable(Type): (string[]))|null  $nodesNamesGetter
     */
    public function map(
        Type $subject,
        callable $cb,
        ?callable $nodesNamesGetter = null,
        bool $preserveAttributes = true,
    ): Type {
        $nodesNamesGetter ??= fn (Type $t) => $t->nodes();

        if ($this->visitedNodesWeakMap->offsetExists($subject)) {
            return $this->visitedNodesWeakMap->offsetGet($subject);
        }

        $subNodes = $nodesNamesGetter($subject);

        $mappedSubject = $cb($subNodes ? clone $subject : $subject);

        if ($preserveAttributes) {
            $mappedSubject->mergeAttributes($subject->attributes());
        }

        $this->visitedNodesWeakMap->offsetSet($subject, $mappedSubject);

        foreach ($nodesNamesGetter($mappedSubject) as $propertyWithNode) {
            $node = $mappedSubject->$propertyWithNode;
            if (! is_array($node)) {
                $mappedSubject->$propertyWithNode = $this->map($node, $cb, $nodesNamesGetter, $preserveAttributes);
            } else {
                foreach ($node as $index => $item) {
                    $mappedSubject->$propertyWithNode[$index] = $this->map($item, $cb, $nodesNamesGetter, $preserveAttributes);
                }
            }
        }

        return $mappedSubject;
    }
}
