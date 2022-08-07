<?php

namespace Dedoc\Documentor\Support\Generator\Types;

abstract class Type
{
    protected string $type;

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
        return [
            'type' => $this->nullable ? [$this->type, 'null'] : $this->type,
        ];
    }
}
