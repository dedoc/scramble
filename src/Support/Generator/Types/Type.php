<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\WithAttributes;
use Dedoc\Scramble\Support\Generator\WithExtensions;

abstract class Type
{
    use WithAttributes;
    use WithExtensions;

    public string $type;

    public string $format = '';

    public string $description = '';

    public string $contentMediaType = '';

    public string $contentEncoding = '';

    /** @var array|scalar|null|MissingValue */
    public $example;

    /** @var array|scalar|null|MissingValue */
    public $default;

    /** @var array<array|scalar|null|MissingValue> */
    public $examples = [];

    public array $enum = [];

    public bool $nullable = false;

    public function __construct(string $type)
    {
        $this->type = $type;
        $this->example = new MissingValue;
        $this->default = new MissingValue;
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

        $this->nullable = $fromType->nullable;
        $this->enum = $fromType->enum;
        $this->description = $fromType->description;
        $this->example = $fromType->example;
        $this->default = $fromType->default;

        return $this;
    }

    public function toArray()
    {
        return array_merge(
            array_filter([
                'type' => $this->nullable ? [$this->type, 'null'] : $this->type,
                'format' => $this->format,
                'contentMediaType' => $this->contentMediaType,
                'contentEncoding' => $this->contentEncoding,
                'description' => $this->description,
                'enum' => count($this->enum) ? $this->enum : null,
            ]),
            $this->example instanceof MissingValue ? [] : ['example' => $this->example],
            $this->default instanceof MissingValue ? [] : ['default' => $this->default],
            count(
                $examples = collect($this->examples)
                    ->reject(fn ($example) => $example instanceof MissingValue)
                    ->values()
                    ->toArray()
            ) ? ['examples' => $examples] : [],
            $this->extensionPropertiesToArray(),
        );
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
}
