<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\WithExtensions;
use JsonSerializable;

class OAuthFlow implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public string $authorizationUrl = '';

    /** OAS 3.2.0+ */
    public ?string $deviceAuthorizationUrl = null;

    public string $tokenUrl = '';

    public string $refreshUrl = '';

    /** @var array<string, string> */
    public array $scopes = [];

    public function authorizationUrl(string $authorizationUrl): OAuthFlow
    {
        $this->authorizationUrl = $authorizationUrl;

        return $this;
    }

    /** @return $this */
    public function deviceAuthorizationUrl(?string $deviceAuthorizationUrl): self
    {
        $this->deviceAuthorizationUrl = $deviceAuthorizationUrl;

        return $this;
    }

    public function tokenUrl(string $tokenUrl): OAuthFlow
    {
        $this->tokenUrl = $tokenUrl;

        return $this;
    }

    public function refreshUrl(string $refreshUrl): OAuthFlow
    {
        $this->refreshUrl = $refreshUrl;

        return $this;
    }

    public function addScope(string $name, string $description = '')
    {
        $this->scopes[$name] = $description;

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
        return $this->serialize(OpenApiVersion::V3_1, fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_2, fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, callable $serializeItem): mixed
    {
        return [
            ...array_filter([
                'authorizationUrl' => $this->authorizationUrl,
                'tokenUrl' => $this->tokenUrl,
                'refreshUrl' => $this->refreshUrl,
            ]),
            ...($version === OpenApiVersion::V3_2 && $this->deviceAuthorizationUrl !== null
                ? ['deviceAuthorizationUrl' => $this->deviceAuthorizationUrl]
                : []),
            // Never filter 'scopes' as it is allowed to be empty. If empty it must be an object
            'scopes' => empty($this->scopes) ? new \stdClass : $this->scopes,
            ...$this->extensionPropertiesToArray(),
        ];
    }
}
