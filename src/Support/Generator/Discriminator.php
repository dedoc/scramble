<?php

namespace Dedoc\Scramble\Support\Generator;

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
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(callable $serializeItem): array
    {
        return array_merge(
            ['propertyName' => $this->propertyName],
            $this->mapping ? ['mapping' => (object) $this->mapping] : [],
            $this->extensionPropertiesToArray(),
        );
    }
}
