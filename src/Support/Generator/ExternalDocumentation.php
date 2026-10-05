<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class ExternalDocumentation implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public string $url,
        public ?string $description = null,
    ) {}

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): array
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): array
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(callable $serializeItem): array
    {
        $result = array_filter([
            'description' => $this->description,
            'url' => $this->url,
        ], fn ($value) => $value !== null);

        return array_merge(
            $result,
            $this->extensionPropertiesToArray(),
        );
    }
}
