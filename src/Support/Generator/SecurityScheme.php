<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\ApiKeySecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\HttpSecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\Oauth2SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OpenIdConnectUrlSecurityScheme;
use JsonSerializable;

class SecurityScheme implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public string $type;

    public string $description = '';

    public string $schemeName = 'scheme';

    public bool $default = false;

    /** OAS 3.2.0+ */
    public ?bool $deprecated = null;

    /** OAS 3.2.0+ */
    public ?string $oauth2MetadataUrl = null;

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

    /** @return $this */
    public function setDeprecated(?bool $deprecated): self
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    /** @return $this */
    public function setOauth2MetadataUrl(?string $oauth2MetadataUrl): self
    {
        $this->oauth2MetadataUrl = $oauth2MetadataUrl;

        return $this;
    }

    public function default(): self
    {
        $this->default = true;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(OpenApiVersion::V31, fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V32, fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, callable $serializeItem): mixed
    {
        $result = array_filter([
            'type' => $this->type,
            'description' => $this->description,
        ]);

        if ($version === OpenApiVersion::V32) {
            $result = array_merge($result, array_filter([
                'deprecated' => $this->deprecated,
                'oauth2MetadataUrl' => $this->oauth2MetadataUrl,
            ], fn ($value) => $value !== null));
        }

        return array_merge($result, $this->extensionPropertiesToArray());
    }
}
