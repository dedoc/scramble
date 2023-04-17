<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class OpenIdConnectUrlSecurityScheme extends SecurityScheme
{
    public string $openIdConnectUrl;

    public function __construct(string $openIdConnectUrl)
    {
        parent::__construct('openIdConnect');

        $this->openIdConnectUrl = $openIdConnectUrl;
    }

    public function toArray(OpenApi $openApi)
    {
        return array_merge(parent::toArray($openApi), [
            'openIdConnectUrl' => $this->openIdConnectUrl,
        ]);
    }
}
