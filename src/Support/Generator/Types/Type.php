<?php

namespace Dedoc\ApiDocs\Support\Generator\Types;

abstract class Type
{
    protected string $type;

    public string $description = '';

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
}
