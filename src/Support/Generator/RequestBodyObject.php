<?php

namespace Dedoc\Scramble\Support\Generator;

class RequestBodyObject
{
    /** @var array<string, Schema> */
    public array $content;

    public string $description = '';

    public static function make()
    {
        return new self;
    }

    public function setContent(string $type, Schema|Reference $schema)
    {
        $this->content[$type] = $schema;

        return $this;
    }

    public function description(string $string)
    {
        $this->description = $string;

        return $this;
    }

    public function toArray()
    {
        $result = array_filter([
            'description' => $this->description,
        ]);

        $content = [];
        foreach ($this->content as $mediaType => $schema) {
            $content[$mediaType] = [
                'schema' => $schema->toArray(),
            ];
        }

        $result['content'] = $content;

        return $result;
    }
}
