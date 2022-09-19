<?php

namespace Dedoc\Scramble\Support\Generator\Types;

abstract class Type
{
    use TypeAttributes;

    protected string $type;

    public string $format = '';

    public string $description = '';

    public array $enum = [];

    public bool $nullable = false;

    public function __construct(string $type)
    {
        $this->type = $type;
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

        return $this;
    }

    public function toArray()
    {
        return array_filter([
            'type' => $this->nullable ? [$this->type, 'null'] : $this->type,
            'format' => $this->format,
            'description' => $this->description,
            'enum' => count($this->enum) ? $this->enum : null,
        ]);
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
}
