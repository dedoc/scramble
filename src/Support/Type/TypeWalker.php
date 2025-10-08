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
                if ($child && $foundType = $this->first($child, $lookup)) {
                    return $foundType;
                }
            }

            return null;
        }, fn () => null);
    }

    /**
     * @param  callable(Type): bool  $lookup
     * @return Type[]
     */
    public function findAll(Type $type, callable $lookup): array
    {
        return RecursionGuard::run($type, function () use ($type, $lookup) {
            $foundTypes = [];

            if ($lookup($type)) {
                $foundTypes[] = $type;
            }

            $publicChildren = collect($type->nodes())
                ->flatMap(fn ($node) => is_array($type->$node) ? array_values($type->$node) : [$type->$node]);

            foreach ($publicChildren as $child) {
                if ($child && $allFoundTypes = $this->findAll($child, $lookup)) {
                    $foundTypes = [...$foundTypes, ...$allFoundTypes];
                }
            }

            return $foundTypes;
        }, fn () => []);
    }

    public function replace(Type $subject, callable $replacer): Type
    {
        if ($replaced = $replacer($subject)) {
            return $replaced;
        }

        if (in_array($subject, $this->visitedNodes, strict: true)) {
            return $subject;
        }
        $this->visitedNodes[] = $subject;

        $propertiesWithNodes = $subject->nodes();

        foreach ($propertiesWithNodes as $propertyWithNode) {
            $node = $subject->$propertyWithNode;
            if (! $node) {
                continue;
            }
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

        $subjectToMap = $subNodes ? clone $subject : $subject;

        $mappedSubject = $cb($subjectToMap);

        if ($preserveAttributes) {
            $mappedSubject->mergeAttributes($subject->attributes());
        }

        if ($subjectToMap !== $mappedSubject) { // type was changed during the mapping
            return $mappedSubject;
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

    /**
     * @param  callable(Type): void  $cb
     * @param  (callable(Type): (string[]))|null  $nodesNamesGetter
     */
    public function walk(
        Type $subject,
        callable $cb,
        ?callable $nodesNamesGetter = null,
    ): void {
        $nodesNamesGetter ??= fn (Type $t) => $t->nodes();

        if ($this->visitedNodesWeakMap->offsetExists($subject)) {
            return;
        }

        $cb($subject);

        $this->visitedNodesWeakMap->offsetSet($subject, $subject);

        foreach ($nodesNamesGetter($subject) as $propertyWithNode) {
            /** @var Type[]|Type $node */
            $node = $subject->$propertyWithNode;
            if (! is_array($node)) {
                $this->walk($node, $cb, $nodesNamesGetter);
            } else {
                foreach ($node as $index => $item) {
                    $this->walk($item, $cb, $nodesNamesGetter);
                }
            }
        }
    }
}
