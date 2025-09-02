<?php

namespace Dedoc\Scramble\Support\Type;

class TypePathItem
{
    const KIND_DEFAULT = 0;

    const KIND_ARRAY_KEY = 1;

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

        if ($this->kind === self::KIND_ARRAY_KEY) {
            if (! $type instanceof KeyedArrayType) {
                return null;
            }

            $matchingItem = collect($type->items)->firstWhere('key', $this->key);

            return $matchingItem?->value;
        }

        if (is_array($type)) {
            return array_key_exists($this->key, $type) ? $type[$this->key] : null;
        }

        return $type->{$this->key} ?? null;
    }
}
