<?php

namespace Dedoc\Scramble\Support\Generator;

class Response
{
    use WithAttributes;
    use WithExtensions;

    public ?int $code = null;

    /** @var array<string, Schema|Reference|null> */
    public array $content;

    public string $description = '';

    /** @var array<string, Header|Reference> */
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

    /**
     * @return $this
     */
    public function addHeader(string $name, Header|Reference $header): self
    {
        $this->headers[$name] = $header;

        return $this;
    }

    /**
     * @return $this
     */
    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);

        return $this;
    }

    /**
     * @param  array<string, Header|Reference>  $headers
     * @return $this
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

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

        $headers = array_map(fn ($header) => $header->toArray(), $this->headers);

        return array_merge(
            $result,
            $headers ? ['headers' => $headers] : [],
            $this->extensionPropertiesToArray(),
        );
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
