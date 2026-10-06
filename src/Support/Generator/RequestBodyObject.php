<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class RequestBodyObject implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public string $description = '';

    /**
     * Entries added through setContent() retain their Schema or Reference value;
     * entries added through addContent() contain a MediaType.
     *
     * @var array<string, MediaType|Schema|Reference>
     */
    public array $content = [];

    /**
     * Determines if the request body is required in the request.
     */
    public bool $required = false;

    /** @return self */
    public static function make()
    {
        return new self;
    }

    /** @return $this */
    public function setContent(string $type, Schema|Reference $schema)
    {
        $this->content[$type] = $schema;

        return $this;
    }

    /** @return $this */
    public function required(bool $required = true)
    {
        $this->required = $required;

        return $this;
    }

    /** @return $this */
    public function description(string $string)
    {
        $this->description = $string;

        return $this;
    }

    public function getContent(string $mediaType): MediaType
    {
        $content = $this->content[$mediaType];

        return $content instanceof MediaType ? $content : new MediaType(schema: $content);
    }

    /**
     * @return $this
     */
    public function addContent(string $type, MediaType $mediaType): self
    {
        $this->content[$type] = $mediaType;

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
        $result = array_filter([
            'description' => $this->description,
            'required' => $this->required,
        ]);

        $content = array_map(
            fn (OpenApiSerializable $item) => $serializeItem($item instanceof MediaType ? $item : new MediaType(schema: $item)),
            $this->content,
        );

        $result['content'] = $content;

        return array_merge($result, $this->extensionPropertiesToArray());
    }
}
