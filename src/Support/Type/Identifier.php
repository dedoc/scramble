<?php

namespace Dedoc\ApiDocs\Support\Type;

class Identifier implements Type
{
    /** @var string */
    public $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
