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
     * entries added through addContent() contain a MediaType or a local media-type reference.
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

    public function getContent(string $mediaType): MediaType|Reference
    {
        $content = $this->content[$mediaType];

        if ($content instanceof MediaType) {
            return $content;
        }

        if ($content instanceof Reference && $content->referenceType === 'mediaTypes') {
            return $content;
        }

        return new MediaType(schema: $content);
    }

    /**
     * @return $this
     */
    public function addContent(string $type, MediaType|Reference $mediaType): self
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

        $result['content'] = [];

        foreach ($this->content as $type => $content) {
            $result['content'][$type] = $serializeItem($this->getContent($type));
        }

        return array_merge($result, $this->extensionPropertiesToArray());
    }
}
