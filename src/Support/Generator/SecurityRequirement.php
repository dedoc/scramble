<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class SecurityRequirement implements JsonSerializable, OpenApiSerializable
{
    /**
     * @var array<string, string[]>
     */
    private array $items = [];

    public function __construct(array|string $items)
    {
        if (is_string($items)) { // keeping backward compatibility with synthetic Security object
            $this->items[$items] = [];
        } else {
            $this->items = $items;
        }
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(callable $serializeItem): mixed
    {
        return count($this->items) ? $this->items : (object) [];
    }
}

// To keep backward compatibility
class_alias(SecurityRequirement::class, 'Dedoc\Scramble\Support\Generator\Security');
