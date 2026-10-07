<?php

namespace Dedoc\Scramble\Support\Generator\Combined;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use InvalidArgumentException;

class AnyOf extends Type
{
    /** @var Type[] */
    public $items;

    public function __construct()
    {
        parent::__construct('anyOf');
        $this->items = [new StringType];
    }

    public function clone(): static
    {
        $clone = parent::clone();
        $clone->items = array_map(
            fn (Type $item) => $item->clone(),
            $clone->items,
        );

        return $clone;
    }

    public function setItems($items)
    {
        if (collect($items)->contains(fn ($item) => ! $item instanceof Type)) {
            throw new InvalidArgumentException('All items should be instances of '.Type::class);
        }

        $this->items = $items;

        return $this;
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(parent::serializeAs31(), fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(parent::serializeAs32(), fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(array $parentArray, callable $serializeItem): mixed
    {
        unset($parentArray['type']);

        $items = array_map(
            $serializeItem,
            $this->items,
        );

        $items = array_map(function ($item) {
            if (
                is_array($item)
                && array_keys($item) === ['type', 'items']
                && $item['type'] === 'array'
                && is_object($item['items'])
                && (array) $item['items'] === []
            ) {
                return ['type' => 'array'];
            }

            return $item;
        }, $items);

        if (count($items) > 1 && collect($items)->every(
            fn ($item) => is_array($item) && array_keys($item) === ['type'] && (is_string($item['type']) || is_array($item['type']))
        )) {
            $types = collect($items)
                ->flatMap(fn ($item) => (array) $item['type'])
                ->unique();

            if ($types->contains('number')) {
                $types = $types->reject(fn ($type) => $type === 'integer');
            }

            $types = $types->values();

            return [
                ...$parentArray,
                'type' => $types->count() === 1 ? $types->first() : $types->all(),
            ];
        }

        return [
            ...$parentArray,
            'anyOf' => $items,
        ];
    }
}
