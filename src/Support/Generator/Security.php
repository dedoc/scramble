<?php

namespace Dedoc\ApiDocs\Support\Generator;

class Security
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function toArray()
    {
        return [
            $this->name => [],
        ];
    }
}
