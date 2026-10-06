<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\ExternalDocumentation;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\WithAttributes;
use Dedoc\Scramble\Support\Generator\WithExtensions;
use Dedoc\Scramble\Support\Generator\Xml;
use JsonSerializable;

abstract class Type implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public string $type;

    public string $format = '';

    public string $description = '';

    public string $contentMediaType = '';

    public string $contentEncoding = '';

    /**
     * @deprecated
     *
     * @var array|scalar|null|MissingValue
     */
    public $example;

    /** @var array|scalar|null|MissingValue */
    public $default;

    /** @var array<array|scalar|null|MissingValue> */
    public $examples = [];

    public array $enum = [];

    /** @var scalar|null|MissingValue */
    public $const;

    public bool $nullable = false;

    public bool $deprecated = false;

    public ?string $pattern = null;

    public ?Discriminator $discriminator = null;

    public ?Xml $xml = null;

    public ?ExternalDocumentation $externalDocs = null;

    public function __construct(string $type)
    {
        $this->type = $type;
        $this->example = new MissingValue; // @phpstan-ignore property.deprecated
        $this->default = new MissingValue;
        $this->const = new MissingValue;
    }

    public function clone(): static
    {
        return clone $this;
    }

    /**
     * @return $this
     */
    public function nullable(bool $nullable): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * @return $this
     */
    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    /**
     * @return $this
     */
    public function contentMediaType(string $mediaType): self
    {
        $this->contentMediaType = $mediaType;

        return $this;
    }

    /**
     * @return $this
     */
    public function contentEncoding(string $encoding): self
    {
        $this->contentEncoding = $encoding;

        return $this;
    }

    /**
     * @return $this
     */
    public function addProperties(Type $fromType): self
    {
        $this->attributes = $fromType->attributes;
        $this->format = $fromType->format;
        $this->description = $fromType->description;
        $this->contentMediaType = $fromType->contentMediaType;
        $this->contentEncoding = $fromType->contentEncoding;
        $this->example = $fromType->example; // @phpstan-ignore property.deprecated, property.deprecated
        $this->default = $fromType->default;
        $this->examples = $fromType->examples;
        $this->enum = $fromType->enum;
        $this->const = $fromType->const;
        $this->nullable = $fromType->nullable;
        $this->deprecated = $fromType->deprecated;
        $this->pattern = $fromType->pattern;
        $this->discriminator = $fromType->discriminator;
        $this->xml = $fromType->xml;
        $this->externalDocs = $fromType->externalDocs;

        return $this;
    }

    public function resolve()
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return $this
     */
    public function enum(array $enum): self
    {
        $this->enum = $enum;

        return $this;
    }

    /**
     * @param  scalar|null|MissingValue  $const
     * @return $this
     */
    public function const($const): self
    {
        $this->const = $const;

        return $this;
    }

    /**
     * @deprecated
     *
     * @param  array|scalar|null|MissingValue  $example
     * @return $this
     */
    public function example($example): self
    {
        $this->example = $example;

        return $this;
    }

    /**
     * @param  array|scalar|null|MissingValue  $default
     * @return $this
     */
    public function default($default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @param  array<array|scalar|null|MissingValue>  $examples
     * @return $this
     */
    public function examples(array $examples): self
    {
        $this->examples = $examples;

        return $this;
    }

    /** @return $this */
    public function deprecated(bool $deprecated): self
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    /** @return $this */
    public function pattern(?string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function setDiscriminator(?Discriminator $discriminator): self
    {
        $this->discriminator = $discriminator;

        return $this;
    }

    public function setXml(?Xml $xml): self
    {
        $this->xml = $xml;

        return $this;
    }

    public function setExternalDocs(?ExternalDocumentation $externalDocs): self
    {
        $this->externalDocs = $externalDocs;

        return $this;
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
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(callable $serializeItem): array
    {
        $enum = $this->enum;
        $const = $this->const;

        if ($this->nullable && ! $const instanceof MissingValue && $const !== null) {
            $enum = [$const, null];
            $const = new MissingValue;
        }

        if ($this->nullable && count($enum) && ! in_array(null, $enum, true)) {
            $enum = [...$enum, null];
        }

        return array_merge(
            array_filter([
                'type' => $this->nullable ? [$this->type, 'null'] : $this->type,
                'format' => $this->format,
                'contentMediaType' => $this->contentMediaType,
                'contentEncoding' => $this->contentEncoding,
                'description' => $this->description,
                'deprecated' => $this->deprecated,
                'pattern' => $this->pattern,
                'enum' => count($enum) ? $enum : null,
            ]),
            $const instanceof MissingValue ? [] : ['const' => $const],
            $this->default instanceof MissingValue ? [] : ['default' => $this->default],
            count(
                $examples = collect($this->examples)
                    ->prepend($this->example) // @phpstan-ignore property.deprecated
                    ->reject(fn ($example) => $example instanceof MissingValue)
                    ->values()
                    ->toArray()
            ) ? ['examples' => $examples] : [],
            $this->discriminator !== null ? ['discriminator' => $serializeItem($this->discriminator)] : [],
            $this->xml !== null ? ['xml' => $serializeItem($this->xml)] : [],
            $this->externalDocs !== null ? ['externalDocs' => $serializeItem($this->externalDocs)] : [],
            $this->extensionPropertiesToArray(),
        );
    }
}
