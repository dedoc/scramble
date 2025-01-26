<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\Support\Generator\SecurityScheme;

class HttpSecurityScheme extends SecurityScheme
{
    public string $scheme;

    public string $bearerFormat = '';

    public function __construct(string $scheme, string $bearerFormat = '')
    {
        parent::__construct('http');

        $this->scheme = $scheme;
        $this->bearerFormat = $bearerFormat;
    }

    public function toArray()
    {
        return array_merge(parent::toArray(), array_filter([
            'scheme' => $this->scheme,
            'bearerFormat' => $this->bearerFormat,
        ]));
    }
}
