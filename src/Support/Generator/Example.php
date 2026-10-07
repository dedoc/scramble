<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
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
        /** OAS 3.2.0+ */
        public mixed $dataValue = new MissingValue,
        /** OAS 3.2.0+ */
        public ?string $serializedValue = null,
    ) {}

    /**
     * @return $this
     */
    public function setDataValue(mixed $dataValue): self
    {
        $this->dataValue = $dataValue;

        return $this;
    }

    /**
     * @return $this
     */
    public function setSerializedValue(?string $serializedValue): self
    {
        $this->serializedValue = $serializedValue;

        return $this;
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
        return $this->serialize(OpenApiVersion::V31, fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V32, fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, callable $serializeItem): mixed
    {
        $result = array_filter(
            ['value' => $this->value],
            fn ($v) => ! $v instanceof MissingValue,
        ) + array_filter([
            'summary' => $this->summary,
            'description' => $this->description,
            'externalValue' => $this->externalValue,
        ]);

        if ($version === OpenApiVersion::V32) {
            if (! $this->dataValue instanceof MissingValue) {
                $result['dataValue'] = $this->dataValue;
            }

            if ($this->serializedValue !== null) {
                $result['serializedValue'] = $this->serializedValue;
            }
        }

        return array_merge($result, $this->extensionPropertiesToArray());
    }
}
