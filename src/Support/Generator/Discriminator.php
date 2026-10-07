<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Discriminator implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public string $propertyName,
        /** @var array<string, string> */
        public array $mapping = [],
        /** OAS 3.2.0+ */
        public ?string $defaultMapping = null,
    ) {}

    public function setPropertyName(string $propertyName): self
    {
        $this->propertyName = $propertyName;

        return $this;
    }

    /**
     * @param  array<string, string>  $mapping
     */
    public function setMapping(array $mapping): self
    {
        $this->mapping = $mapping;

        return $this;
    }

    public function addMapping(string $value, string $schema): self
    {
        $this->mapping[$value] = $schema;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDefaultMapping(?string $defaultMapping): self
    {
        $this->defaultMapping = $defaultMapping;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_1, fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_2, fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, callable $serializeItem): array
    {
        return array_merge(
            ['propertyName' => $this->propertyName],
            $this->mapping ? ['mapping' => (object) $this->mapping] : [],
            $version === OpenApiVersion::V3_2 && $this->defaultMapping !== null
                ? ['defaultMapping' => $this->defaultMapping]
                : [],
            $this->extensionPropertiesToArray(),
        );
    }
}
