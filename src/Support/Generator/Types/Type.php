<?php

namespace Dedoc\Documentor\Support\Generator\Types;

abstract class Type
{
    protected string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function toArray()
    {
        return [
            'type' => $this->type,
        ];
    }
}
