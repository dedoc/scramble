<?php

namespace Dedoc\Scramble\Support\Generator\Types;

abstract class Type
{
    use TypeAttributes;

    protected string $type;

    public string $description = '';

    public array $enum = [];

    /**
     * Hint is used to pass a description to other handlers but not show it with type.
     * For example, for response documentation.
     */
    public string $hint = '';

    protected bool $nullable = false;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function nullable(bool $nullable)
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function toArray()
    {
        return array_filter([
            'type' => $this->nullable ? [$this->type, 'null'] : $this->type,
            'description' => $this->description,
            'enum' => count($this->enum) ? $this->enum : null,
        ]);
    }

    public function setDescription(string $description): Type
    {
        $this->description = $description;

        return $this;
    }

    public function setHint(string $hint): Type
    {
        $this->hint = $hint;

        return $this;
    }

    public function enum(array $enum): Type
    {
        $this->enum = $enum;

        return $this;
    }
}
