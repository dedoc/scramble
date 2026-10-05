<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class RequestBodyObject implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public string $description = '';

    /** @var array<string, Schema|Reference> */
    public array $content = [];

    /**
     * Determines if the request body is required in the request.
     */
    public bool $required = false;

    public static function make()
    {
        return new self;
    }

    public function setContent(string $type, Schema|Reference $schema)
    {
        $this->content[$type] = $schema;

        return $this;
    }

    public function required(bool $required = true)
    {
        $this->required = $required;

        return $this;
    }

    public function description(string $string)
    {
        $this->description = $string;

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
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(callable $serializeItem): mixed
    {
        $result = array_filter([
            'description' => $this->description,
            'required' => $this->required,
        ]);

        $content = [];
        foreach ($this->content as $mediaType => $schema) {
            $content[$mediaType] = [
                'schema' => $serializeItem($schema),
            ];
        }

        $result['content'] = $content;

        return array_merge($result, $this->extensionPropertiesToArray());
    }
}
