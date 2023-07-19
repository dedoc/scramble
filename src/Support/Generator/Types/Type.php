<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\MissingExample;
use Dedoc\Scramble\Support\Generator\WithAttributes;

abstract class Type
{
    use WithAttributes;

    protected string $type;

    public string $format = '';

    public string $description = '';

    /** @var array|scalar|null|MissingExample */
    public $example;

    public array $enum = [];

    public bool $nullable = false;

    public function __construct(string $type)
    {
        $this->type = $type;
        $this->example = new MissingExample;
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

    public function addProperties(Type $fromType)
    {
        $this->attributes = $fromType->attributes;

        $this->nullable = $fromType->nullable;
        $this->enum = $fromType->enum;
        $this->description = $fromType->description;
        $this->example = $fromType->example;

        return $this;
    }

    public function toArray()
    {
        return array_merge(array_filter([
            'type' => $this->nullable ? [$this->type, 'null'] : $this->type,
            'format' => $this->format,
            'description' => $this->description,
            'enum' => count($this->enum) ? $this->enum : null,
        ]), $this->example instanceof MissingExample ? [] : ['example' => $this->example]);
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
}
