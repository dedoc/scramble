<?php

namespace Dedoc\Scramble\Support\Generator;

class Response
{
    public ?int $code = null;

    public ?Reference $reference = null;

    /** @var array<string, Schema|null> */
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
     * @param  Schema|null  $schema
     */
    public function setContent(string $type, $schema)
    {
        $this->content[$type] = $schema;

        return $this;
    }

    public function setReference(?Reference $reference)
    {
        $this->reference = $reference;

        return $this;
    }

    public function toArray(OpenApi $openApi)
    {
        if ($this->reference) {
            return $this->reference->toArray($openApi);
        }

        $result = [
            'description' => $this->description,
        ];

        $content = [];
        foreach ($this->content as $mediaType => $schema) {
            $content[$mediaType] = $schema ? ['schema' => $schema->toArray($openApi)] : [];
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
