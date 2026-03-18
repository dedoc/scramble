<?php

namespace Dedoc\Scramble\Support\Type;

class TypePathItem
{
    public function __construct(
        public string|int $key,
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

        if (is_array($type)) {
            return array_key_exists($this->key, $type) ? $type[$this->key] : null;
        }

        return $type->{$this->key} ?? null;
    }
}
