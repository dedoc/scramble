<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\WithExtensions;
use JsonSerializable;

class OAuthFlows implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public ?OAuthFlow $implicit = null;

    public ?OAuthFlow $password = null;

    public ?OAuthFlow $clientCredentials = null;

    public ?OAuthFlow $authorizationCode = null;

    /** OAS 3.2.0+ */
    public ?OAuthFlow $deviceAuthorization = null;

    public function implicit(?OAuthFlow $flow): OAuthFlows
    {
        $this->implicit = $flow;

        return $this;
    }

    public function password(?OAuthFlow $flow): OAuthFlows
    {
        $this->password = $flow;

        return $this;
    }

    public function clientCredentials(?OAuthFlow $flow): OAuthFlows
    {
        $this->clientCredentials = $flow;

        return $this;
    }

    public function authorizationCode(?OAuthFlow $flow): OAuthFlows
    {
        $this->authorizationCode = $flow;

        return $this;
    }

    /** @return $this */
    public function deviceAuthorization(?OAuthFlow $flow): self
    {
        $this->deviceAuthorization = $flow;

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
        return array_merge(array_map(
            $serializeItem,
            array_filter([
                'implicit' => $this->implicit,
                'password' => $this->password,
                'clientCredentials' => $this->clientCredentials,
                'authorizationCode' => $this->authorizationCode,
                'deviceAuthorization' => $version === OpenApiVersion::V3_2 ? $this->deviceAuthorization : null,
            ])
        ), $this->extensionPropertiesToArray());
    }
}
