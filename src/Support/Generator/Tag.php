<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Tag implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?ExternalDocumentation $externalDocs = null,
        /** OAS 3.2.0+ */
        public ?string $summary = null,
        /** OAS 3.2.0+ */
        public ?string $parent = null,
        /** OAS 3.2.0+ */
        public ?string $kind = null,
    ) {}

    /**
     * @return $this
     */
    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * @return $this
     */
    public function setParent(?string $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return $this
     */
    public function setKind(?string $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function setExternalDocs(?ExternalDocumentation $externalDocs): self
    {
        $this->externalDocs = $externalDocs;

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
    private function serialize(OpenApiVersion $version, callable $serializeItem): array
    {
        $result = array_filter([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        if ($this->externalDocs) {
            $result['externalDocs'] = $serializeItem($this->externalDocs);
        }

        if ($version === OpenApiVersion::V3_2) {
            $result = array_merge($result, array_filter([
                'summary' => $this->summary,
                'parent' => $this->parent,
                'kind' => $this->kind,
            ], fn ($value) => $value !== null));
        }

        return array_merge(
            $result,
            $this->extensionPropertiesToArray(),
        );
    }
}
