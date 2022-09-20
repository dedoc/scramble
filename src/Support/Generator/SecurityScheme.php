<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\SecuritySchemes\ApiKeySecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\HttpSecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\Oauth2SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OpenIdConnectUrlSecurityScheme;

class SecurityScheme
{
    public string $type;

    public string $description = '';

    public string $schemeName = 'scheme';

    public bool $default = false;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public static function apiKey(string $in, string $name)
    {
        return (new ApiKeySecurityScheme($in, $name))->as('apiKey');
    }

    public static function http(string $scheme, string $bearerFormat = '')
    {
        return (new HttpSecurityScheme($scheme, $bearerFormat))->as('http');
    }

    public static function oauth2()
    {
        return (new Oauth2SecurityScheme)->as('oauth2');
    }

    public static function openIdConnect(string $openIdConnectUrl)
    {
        return (new OpenIdConnectUrlSecurityScheme($openIdConnectUrl))->as('openIdConnect');
    }

    public static function mutualTLS()
    {
        return (new static('mutualTLS'))->as('mutualTLS');
    }

    public function as(string $schemeName): self
    {
        $this->schemeName = $schemeName;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function default(): self
    {
        $this->default = true;

        return $this;
    }

    public function toArray()
    {
        return array_filter([
            'type' => $this->type,
            'description' => $this->description,
        ]);
    }
}
