<?php

namespace Dedoc\Scramble\Support\Generator;

class Response
{
    use WithAttributes;
    use WithExtensions;

    public int|string|null $code = null;

    /** @var array<string, MediaType> */
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
    public function setDescription(string $string): self
    {
        $this->description = $string;

        return $this;
    }

    /**
     * @return $this
     */
    public function addContent(string $type, MediaType $mediaType): self
    {
        $this->content[$type] = $mediaType;

        return $this;
    }

    public function setContent($content): self // @phpstan-ignore missingType.parameter
    {
        /**
         * @todo backward compatibility, remove in 1.0
         */
        if (count($args = func_get_args()) === 2) {
            $mediaType = $args[1] instanceof MediaType ? $args[1] : new MediaType(schema: $args[1]); // @phpstan-ignore argument.type

            return $this->addContent($args[0], $mediaType); // @phpstan-ignore argument.type
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
            $result['content'] = array_map(fn ($mt) => $mt->toArray(), $this->content);
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
