<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class OpenIdConnectUrlSecurityScheme extends SecurityScheme
{
    public string $openIdConnectUrl;

    public function __construct(string $openIdConnectUrl)
    {
        parent::__construct('openIdConnect');

        $this->openIdConnectUrl = $openIdConnectUrl;
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
        return array_merge($parentArray, [
            'openIdConnectUrl' => $this->openIdConnectUrl,
        ]);
    }
}
