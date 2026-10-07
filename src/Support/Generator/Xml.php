<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
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
        /** @deprecated In OAS 3.2, use nodeType instead. */
        public ?bool $attribute = null,
        /** @deprecated In OAS 3.2, use nodeType instead. */
        public ?bool $wrapped = null,
        /** OAS 3.2.0+ */
        public ?string $nodeType = null,
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

    /** @deprecated In OAS 3.2, use setNodeType instead. */
    public function setAttribute(?bool $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    /** @deprecated In OAS 3.2, use setNodeType instead. */
    public function setWrapped(?bool $wrapped): self
    {
        $this->wrapped = $wrapped;

        return $this;
    }

    /**
     * @return $this
     */
    public function setNodeType(?string $nodeType): self
    {
        $this->nodeType = $nodeType;

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
        return $this->serialize(OpenApiVersion::V3_1, fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_2, fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, callable $serializeItem): mixed
    {
        $hasNodeType = $version === OpenApiVersion::V3_2 && $this->nodeType !== null;

        $result = array_merge(
            array_filter([
                'name' => $this->name,
                'namespace' => $this->namespace,
                'prefix' => $this->prefix,
                'attribute' => $hasNodeType ? null : $this->attribute,
                'wrapped' => $hasNodeType ? null : $this->wrapped,
                'nodeType' => $hasNodeType ? $this->nodeType : null,
            ], fn ($value) => $value !== null),
            $this->extensionPropertiesToArray(),
        );

        return $result ?: (object) [];
    }
}
