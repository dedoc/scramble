<?php

namespace Dedoc\Scramble\Support\Generator;

class RequestBodyObject
{
    public string $description = '';

    /** @var array<string, Schema> */
    public array $content;

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

    public function toArray()
    {
        $result = array_filter([
            'description' => $this->description,
            'required' => $this->required,
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
