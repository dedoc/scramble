<?php

namespace Dedoc\Scramble\Support\Generator;

class SecurityScheme
{
    public string $type;

    public string $name;

    public string $in;

    public string $description = '';

    public string $schemeName = 'scheme';

    public bool $isDefault = false;

    private function __construct(string $type)
    {
        $this->type = $type;
    }

    public static function apiKey(string $in, string $name)
    {
        $scheme = new self('apiKey');
        $scheme->schemeName = 'apiKey';
        $scheme->in = $in;
        $scheme->name = $name;

        return $scheme;
    }

    public function as(string $schemeName): SecurityScheme
    {
        $this->schemeName = $schemeName;

        return $this;
    }

    public function setDescription(string $description): SecurityScheme
    {
        $this->description = $description;

        return $this;
    }

    public function default(): SecurityScheme
    {
        $this->default = true;

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
