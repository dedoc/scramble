<?php

namespace Dedoc\Scramble\Support\Generator;

class SecurityScheme
{
    private string $type;

    private string $name;

    private string $in;

    private string $description = '';

    private function __construct(string $type)
    {
        $this->type = $type;
    }

    public static function apiKey(string $in, string $name)
    {
        $scheme = new self('apiKey');
        $scheme->in = $in;
        $scheme->name = $name;

        return $scheme;
    }

    public function setDescription(string $description): SecurityScheme
    {
        $this->description = $description;

        return $this;
    }

    public function toArray()
    {
        return array_filter([
            'type' => $this->type,
            'in' => $this->in,
            'name' => $this->name,
            'description' => $this->description,
        ]);
    }
}
