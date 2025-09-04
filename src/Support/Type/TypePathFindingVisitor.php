<?php

namespace Dedoc\Scramble\Support\Type;

use Closure;
use WeakMap;

class TypePathFindingVisitor implements TypeVisitor
{
    /** @var TypePathItem[]|null */
    private ?array $foundPathItems = null;

    /** @var TypePathItem[]|null */
    private ?array $pathItems = null;

    /**
     * @var WeakMap<Type, TypePathItem[]>
     */
    private WeakMap $pointers;

    /**
     * @param  Closure(Type): bool  $cb
     */
    public function __construct(
        private Closure $cb,
    ) {
        $this->pointers = new WeakMap;
    }

    public function enter(Type $type): ?Type
    {
        if ($this->foundPathItems !== null) {
            return null;
        }

        $this->pushPointers($type);

        $this->pushPathItem($type);

        if (($this->cb)($type)) {
            $this->foundPathItems = $this->pathItems ?: [];
        }

        return null;
    }

    public function leave(Type $type): ?Type
    {
        $this->popPathItem($type);

        return null;
    }

    public function getPath(): ?TypePath
    {
        if ($this->foundPathItems === null) {
            return null;
        }

        return new TypePath($this->foundPathItems);
    }

    private function pushPointers(Type $type): void
    {
        if ($type instanceof ArrayItemType_) {
            return;
        }

        if ($type instanceof KeyedArrayType) {
            foreach ($type->items as $item) {
                if ($item->key === null) {
                    // bug?
                    continue;
                }

                $this->pointers->offsetSet(
                    $item->value,
                    [
                        new TypePathItem(
                            key: $item->key,
                            kind: TypePathItem::KIND_ARRAY_KEY,
                        ),
                    ]
                );
            }

            return;
        }

        $propertiesWithNodes = $type->nodes();

        foreach ($propertiesWithNodes as $propertyWithNode) {
            /** @var Type|Type[] $node */
            $node = $type->$propertyWithNode;

            if (! is_array($node)) {
                $this->pointers->offsetSet(
                    $node,
                    [
                        new TypePathItem(
                            key: $propertyWithNode,
                            condition: new TypePathItemCondition(
                                class: $type::class,
                                objectName: $type instanceof ObjectType ? $type->name : null,
                            )
                        ),
                    ]
                );
            } else {
                foreach ($node as $index => $item) {
                    $this->pointers->offsetSet(
                        $item,
                        [
                            new TypePathItem(
                                key: $propertyWithNode,
                                condition: new TypePathItemCondition(
                                    class: $type::class,
                                    objectName: $type instanceof ObjectType ? $type->name : null,
                                )
                            ),
                            new TypePathItem(
                                key: $index,
                            ),
                        ]
                    );
                }
            }
        }
    }

    private function pushPathItem(Type $type): void
    {
        $pointer = $this->pointers[$type] ?? null;

        if ($pointer) {
            $this->pathItems ??= [];

            $this->pathItems = array_merge($this->pathItems, $pointer);
        }
    }

    private function popPathItem(Type $type): void
    {
        $pointer = $this->pointers[$type] ?? null;

        if ($pointer && $this->pathItems) {
            array_splice($this->pathItems, 0 - count($pointer));
        }
    }
}
