<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\Support\Generator\SecurityScheme;

class ApiKeySecurityScheme extends SecurityScheme
{
    public string $name;

    public string $in;

    public function __construct(string $in, string $name)
    {
        parent::__construct('apiKey');

        $this->in = $in;
        $this->name = $name;
    }

    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'in' => $this->in,
            'name' => $this->name,
        ]);
    }
}
