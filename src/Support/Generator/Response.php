<?php

namespace Dedoc\Scramble\Support\Generator;

class Response
{
    public int|string|null $code = null;

    /** @var array<string, Schema|Reference|null> */
    public array $content;

    /** @var array<string, Schema|Reference|null> */
    public array $headers = [];

    public string $description = '';

    public function __construct(int|string|null $code)
    {
        $this->code = $code;
    }

    public static function make(int|string|null $code)
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

    /**
     * @param  Schema|Reference|null  $schema
     */
    public function setHeaders(string $type, $schema)
    {
        $this->headers[$type] = $schema;

        return $this;
    }

    public function toArray()
    {
        $result = [
            'description' => $this->description,
        ];

        if (isset($this->content)) {
            $content = [];
            foreach ($this->content ?? [] as $mediaType => $schema) {
                $content[$mediaType] = $schema ? ['schema' => $schema->toArray()] : (object) [];
            }
            $result['content'] = $content;
        }

        if (isset($this->headers)) {
            $headers = array_map(function ($schema) {
                return $schema ? ['schema' => $schema->toArray()] : (object)[];
            }, $this->headers);
            $result['headers'] = $headers;
        }

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
