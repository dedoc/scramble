<?php

namespace Dedoc\Scramble\Support\Generator;

class ServerVariable
{
    public string $default;

    public array $enum = [];

    public ?string $description = null;

    public function __construct(
        string $default,
        array $enum = [],
        string $description = null
    ) {
        $this->default = $default;
        $this->enum = $enum;
        $this->description = $description;
    }

    public static function make(
        string $default,
        array $enum = [],
        string $description = null
    ) {
        return new self($default, $enum, $description);
    }

    public function toArray()
    {
        return array_filter([
            'default' => $this->default,
            'enum' => count($this->enum) ? $this->enum : null,
            'description' => $this->description,
        ]);
    }
}
