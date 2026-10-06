<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class ServerVariable implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    /**
     * @param  non-empty-array<string>|null  $enum
     */
    public function __construct(
        public string $default,
        public ?array $enum = null,
        public ?string $description = null
    ) {}

    /**
     * @param  non-empty-array<string>|null  $enum
     */
    public static function make(
        string $default,
        ?array $enum = null,
        ?string $description = null
    ) {
        return new self($default, $enum, $description);
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
        $result = array_merge(['default' => $this->default], array_filter([
            'enum' => $this->enum && count($this->enum) ? $this->enum : null,
            'description' => $this->description,
        ]));

        return array_merge(
            $result,
            $this->extensionPropertiesToArray()
        );
    }
}
