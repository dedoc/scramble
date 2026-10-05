<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Example implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public function __construct(
        public mixed $value = new MissingValue,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $externalValue = null,
    ) {}

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
        $result = array_filter(
            ['value' => $this->value],
            fn ($v) => ! $v instanceof MissingValue,
        ) + array_filter([
            'summary' => $this->summary,
            'description' => $this->description,
            'externalValue' => $this->externalValue,
        ]);

        return array_merge($result, $this->extensionPropertiesToArray());
    }
}
