<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

class OAuthFlows
{
    public ?OAuthFlow $implicit = null;

    public ?OAuthFlow $password = null;

    public ?OAuthFlow $clientCredentials = null;

    public ?OAuthFlow $authorizationCode = null;

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

    public function toArray()
    {
        return array_map(
            fn ($f) => $f->toArray(),
            array_filter([
                'implicit' => $this->implicit,
                'password' => $this->password,
                'clientCredentials' => $this->clientCredentials,
                'authorizationCode' => $this->authorizationCode,
            ])
        );
    }
}
