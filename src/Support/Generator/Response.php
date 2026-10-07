<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\Types\Type;
use JsonSerializable;

class Response implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public int|string|null $code = null;

    /**
     * Entries added through setContent() retain their Schema or Reference value;
     * entries added through addContent() contain a MediaType or a local media-type reference. Use getContent()
     * to retrieve the schema regardless of how the entry was added, and
     * getMediaType() to retrieve the MediaType object.
     *
     * @var array<string, MediaType|Schema|Reference>
     */
    public array $content = [];

    /** OAS 3.2.0+ */
    public ?string $summary = null;

    public string $description = '';

    /** @var array<string, Header|Reference> */
    public array $headers = [];

    /** @var array<string, Link|Reference> */
    public array $links = [];

    public function __construct(int|string|null $code)
    {
        $this->code = $code;
    }

    /** @return self */
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

    /** @return $this */
    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

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

    public function getContent(string $mediaType)
    {
        $content = $this->content[$mediaType];

        return $content instanceof MediaType ? $content->schema : $content;
    }

    /**
     * @return $this
     */
    public function addContent(string $type, MediaType|Reference $mediaType): self
    {
        $this->content[$type] = $mediaType;

        return $this;
    }

    /**
     * Returns the stored MediaType or its reference, or a new temporary wrapper for a schema.
     * The wrapper shares the original schema object, but replacing its schema or changing
     * its media type metadata does not update this response.
     *
     * In a future breaking release, getContent() will return the MediaType instead of
     * its schema. This method will then remain as a deprecated forwarding alias
     * during the migration period.
     */
    public function getMediaType(string $mediaType): MediaType|Reference
    {
        $content = $this->content[$mediaType];

        if ($content instanceof MediaType) {
            return $content;
        }

        if ($content instanceof Reference && $content->referenceType === 'mediaTypes') {
            return $content;
        }

        return new MediaType(schema: $this->wrapSchema($content));
    }

    /**
     * @deprecated Use `setDescription` instead.
     */
    public function description(string $string)
    {
        return $this->setDescription($string);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_1, fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_2, fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, callable $serializeItem): mixed
    {
        $result = [
            'description' => $this->description,
        ];

        if ($version === OpenApiVersion::V3_2 && $this->summary !== null) {
            $result['summary'] = $this->summary;
        }

        foreach ($this->content as $type => $content) {
            $result['content'][$type] = $serializeItem($this->getMediaType($type));
        }

        $headers = array_map($serializeItem, $this->headers);
        $links = array_map($serializeItem, $this->links);

        return array_merge(
            $result,
            $headers ? ['headers' => $headers] : [],
            $links ? ['links' => $links] : [],
            $this->extensionPropertiesToArray(),
        );
    }

    private function wrapSchema(Schema|Type|Reference $item): Schema|Reference
    {
        if ($item instanceof Schema || $item instanceof Reference) {
            return $item;
        }

        return Schema::fromType($item);
    }
}
