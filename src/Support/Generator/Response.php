<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Types\Type;

class Response
{
    public ?int $code = null;

    /** @var array<string, Schema|Reference|null> */
    public array $content;

    public string $description = '';

    /** @var array<string, Parameter> */
    public array $headers = [];

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

    public function registerHeader(string $name, ?string $description, Type $type)
    {
        $this->headers[$name] = Parameter::make($name, 'body-header')
            ->description($description)
            ->setSchema(Schema::fromType($type));

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

        if (count($this->headers) > 0) {
            $headers = collect($this->headers)->mapWithKeys(fn (Parameter $header, $key) => [
                $header->name => [
                    'description' => $header->description,
                    'schema' => $header->schema->toArray()
                ]
            ])
                ->toArray();
            $result['headers'] = $headers;
        }

        return $result;
    }

    public function getContent(string $mediaType)
    {
        return $this->content[$mediaType];
    }

    public function getHeader(string $header)
    {
        return $this->headers[$header];
    }

    public function description(string $string)
    {
        $this->description = $string;

        return $this;
    }
}
