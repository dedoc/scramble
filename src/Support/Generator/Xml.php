<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Xml implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public ?string $name = null,
        public ?string $namespace = null,
        public ?string $prefix = null,
        public ?bool $attribute = null,
        public ?bool $wrapped = null,
    ) {}

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setNamespace(?string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function setPrefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function setAttribute(?bool $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function setWrapped(?bool $wrapped): self
    {
        $this->wrapped = $wrapped;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray(): mixed
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
        $result = array_merge(
            array_filter([
                'name' => $this->name,
                'namespace' => $this->namespace,
                'prefix' => $this->prefix,
                'attribute' => $this->attribute,
                'wrapped' => $this->wrapped,
            ], fn ($value) => $value !== null),
            $this->extensionPropertiesToArray(),
        );

        return $result ?: (object) [];
    }
}
