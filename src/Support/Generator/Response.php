<?php

namespace Dedoc\Scramble\Support\Generator;

class Response
{
    use WithAttributes;
    use WithExtensions;

    public int|string|null $code = null;

    /** @var array<string, Schema|Reference> */
    public array $content = [];

    public string $description = '';

    /** @var array<string, Header|Reference> */
    public array $headers = [];

    /** @var array<string, Link|Reference> */
    public array $links = [];

    public function __construct(int|string|null $code)
    {
        $this->code = $code;
    }

    public static function make(int|string|null $code)
    {
        return new self($code);
    }

    /**
     * @return $this
     */
    public function setCode(int|string|null $code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDescription(string $string): self
    {
        $this->description = $string;

        return $this;
    }

    /**
     * @param  Schema|Reference  $schema
     * @return $this
     */
    public function setContent(string $type, $schema): self
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

    /**
     * @return $this
     */
    public function addLink(string $name, Link|Reference $link): self
    {
        $this->links[$name] = $link;

        return $this;
    }

    /**
     * @return $this
     */
    public function removeLink(string $name): self
    {
        unset($this->links[$name]);

        return $this;
    }

    /**
     * @param  array<string, Link|Reference>  $links
     * @return $this
     */
    public function setLinks(array $links): self
    {
        $this->links = $links;

        return $this;
    }

    public function toArray()
    {
        $result = [
            'description' => $this->description,
        ];

        if (count($this->content)) {
            $result['content'] = array_map(fn ($c) => ['schema' => $c->toArray()], $this->content);
        }

        $headers = array_map(fn ($header) => $header->toArray(), $this->headers);
        $links = array_map(fn ($link) => $link->toArray(), $this->links);

        return array_merge(
            $result,
            $headers ? ['headers' => $headers] : [],
            $links ? ['links' => $links] : [],
            $this->extensionPropertiesToArray(),
        );
    }

    public function getContent(string $mediaType)
    {
        return $this->content[$mediaType];
    }

    /**
     * @deprecated Use `setDescription` instead.
     */
    public function description(string $string)
    {
        return $this->setDescription($string);
    }
}
