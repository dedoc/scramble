<?php

namespace Dedoc\Scramble\Support\Generator;

class Response
{
    use WithAttributes;
    use WithExtensions;

    public ?int $code = null;

    /** @var array<string, MediaType> */
    public array $content = [];

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
     * @return $this
     */
    public function addContent(string $type, MediaType $mediaType): self
    {
        $this->content[$type] = $mediaType;

        return $this;
    }

    public function setContent($content): self
    {
        /**
         * @todo backward compatibility, remove in 1.0
         */
        if (count($args = func_get_args()) === 2) {
            $mediaType = $args[1] instanceof MediaType ? $args[1] : new MediaType(schema: $args[1]);

            return $this->addContent($args[0], $mediaType);
        }

        $this->content = $content;

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

        if (count($this->content)) {
            $result['content'] = array_map(fn ($mt) => $mt->toArray(), $this->content);
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
