<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\MissingExample;
use Dedoc\Scramble\Support\Generator\WithAttributes;

abstract class Type
{
    use WithAttributes;

    public string $type;

    public string $format = '';

    public string $description = '';

    public string $contentMediaType = '';

    public string $contentEncoding = '';

    /** @var array|scalar|null|MissingExample */
    public $example;

    /** @var array|scalar|null|MissingExample */
    public $default;

    /** @var array<array|scalar|null|MissingExample> */
    public $examples = [];

    public array $enum = [];

    public bool $nullable = false;

    public function __construct(string $type)
    {
        $this->type = $type;
        $this->example = new MissingExample;
        $this->default = new MissingExample;
    }

    public function nullable(bool $nullable)
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function format(string $format)
    {
        $this->format = $format;

        return $this;
    }

    public function contentMediaType(string $mediaType)
    {
        $this->contentMediaType = $mediaType;

        return $this;
    }

    public function contentEncoding(string $encoding)
    {
        $this->contentEncoding = $encoding;

        return $this;
    }

    public function addProperties(Type $fromType)
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
                'type' => $this->type,
                'nullable' => $this->nullable,
                'format' => $this->format,
                'contentMediaType' => $this->contentMediaType,
                'contentEncoding' => $this->contentEncoding,
                'description' => $this->description,
                'enum' => count($this->enum) ? $this->enum : null,
            ]),
            $this->example instanceof MissingExample ? [] : ['example' => $this->example],
            $this->default instanceof MissingExample ? [] : ['default' => $this->default],
            count(
                $examples = collect($this->examples)
                    ->reject(fn ($example) => $example instanceof MissingExample)
                    ->values()
                    ->toArray()
            ) ? ['examples' => $examples] : [],
        );
    }

    public function setDescription(string $description): Type
    {
        $this->description = $description;

        return $this;
    }

    public function enum(array $enum): Type
    {
        $this->enum = $enum;

        return $this;
    }

    /**
     * @param  array|scalar|null|MissingExample  $example
     */
    public function example($example)
    {
        $this->example = $example;

        return $this;
    }

    /**
     * @param  array|scalar|null|MissingExample  $default
     */
    public function default($default)
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @param  array<array|scalar|null|MissingExample>  $examples
     */
    public function examples(array $examples)
    {
        $this->examples = $examples;

        return $this;
    }
}
