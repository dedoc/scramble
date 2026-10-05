<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
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

    public function serializeAs31(): mixed
    {
        return $this->serialize(parent::serializeAs31(), fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(parent::serializeAs32(), fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(array $parentArray, callable $serializeItem): mixed
    {
        return array_merge($parentArray, array_filter([
            'scheme' => $this->scheme,
            'bearerFormat' => $this->bearerFormat,
        ]));
    }
}
