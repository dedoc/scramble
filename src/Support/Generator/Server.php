<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Server implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public string $url;

    public string $description = '';

    /**
     * @var array<string, ServerVariable>
     */
    public array $variables = [];

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public static function make(string $url)
    {
        return new self($url);
    }

    public function setDescription(string $description): Server
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<string, ServerVariable>  $variables
     */
    public function variables(array $variables)
    {
        $this->variables = $variables;

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
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(callable $serializeItem): mixed
    {
        return array_merge(array_filter([
            'url' => $this->url,
            'description' => $this->description,
            'variables' => count($this->variables)
                ? array_map($serializeItem, $this->variables)
                : null,
        ]), $this->extensionPropertiesToArray());
    }
}
