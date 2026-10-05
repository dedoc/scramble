<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Callback implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public function __construct(
        /** @var array<string, Path|Reference> */
        public array $paths = [],
    ) {}

    public function addPath(string $expression, Path|Reference $path): self
    {
        $this->paths[$expression] = $path;

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
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(callable $serializeItem): array
    {
        return array_replace(
            array_map($serializeItem, $this->paths),
            $this->extensionPropertiesToArray(),
        );
    }
}
