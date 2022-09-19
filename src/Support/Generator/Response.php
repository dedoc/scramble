<?php

namespace Dedoc\Scramble\Support\Generator;

class Response
{
    public ?int $code = null;

    /** @var array<string, Schema|Reference|null> */
    public array $content;

    public string $description = '';

    public function __construct(?int $code)
    {
        $this->code = $code;
    }

    public static function make(?int $code)
    {
        return new self($code);
    }

    /**
     * @param  Schema|Reference|null  $schema
     */
    public function setContent(string $type, $schema)
    {
        $this->content[$type] = $schema;

        return $this;
    }

    public function toArray()
    {
        $result = [
            'description' => $this->description,
        ];

        $content = [];
        foreach ($this->content as $mediaType => $schema) {
            $content[$mediaType] = $schema ? ['schema' => $schema->toArray()] : [];
        }

        $result['content'] = $content;

        return $result;
    }

    public function getContent(string $mediaType)
    {
        return $this->content[$mediaType];
    }

    public function description(string $string)
    {
        $this->description = $string;

        return $this;
    }
}
