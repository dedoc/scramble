<?php

namespace Dedoc\Documentor\Support\Generator;

class Response
{
    public ?int $code = null;

    /** @var array<string, Schema> */
    private array $content;

    public function __construct(?int $code)
    {
        $this->code = $code;
    }

    public static function make(?int $code)
    {
        return new self($code);
    }

    public function setContent(string $type, Schema $schema)
    {
        $this->content[$type] = $schema;

        return $this;
    }

    public function toArray()
    {
        $result = [
            //            'description' => '',
        ];

        $content = [];
        foreach ($this->content as $mediaType => $schema) {
            $content[$mediaType] = [
                'schema' => $schema->toArray(),
            ];
        }

        $result['content'] = $content;

        return $result;
    }

    public function getContent(string $mediaType)
    {
        return $this->content[$mediaType];
    }
}
