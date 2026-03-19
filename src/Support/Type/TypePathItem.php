<?php

namespace Dedoc\Scramble\Support\Type;

class TypePathItem
{
    const KIND_DEFAULT = 0;

    const KIND_ARRAY_ITEM = 1;

    public function __construct(
        public string|int $key,
        public int $kind = self::KIND_DEFAULT,
        public ?TypePathItemCondition $condition = null,
    ) {}

    /**
     * @param  Type[]|Type  $type
     * @return Type|Type[]|null
     */
    public function getFrom(array|Type $type): Type|array|null
    {
        if ($this->condition && $type instanceof Type && ! $this->condition->matches($type)) {
            return null;
        }

        if ($this->kind === self::KIND_ARRAY_ITEM) {
            if (! is_array($type)) {
                // fail!
                return null;
            }

            foreach ($type as $index => $arrayItem) {
                if (! $arrayItem instanceof ArrayItemType_) {
                    // fail?
                    continue;
                }

                $itemKey = $arrayItem->key ?? $index;

                if ($this->key === $itemKey) {
                    return $arrayItem;
                }
            }

            return null;
        }

        if (is_array($type)) {
            return array_key_exists($this->key, $type) ? $type[$this->key] : null;
        }

        return $type->{$this->key} ?? null;
    }
}
