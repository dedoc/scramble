<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

class OAuthFlow
{
    public string $authorizationUrl = '';

    public string $tokenUrl = '';

    public string $refreshUrl = '';

    /** @var array<string, string> */
    public array $scopes = [];

    public function authorizationUrl(string $authorizationUrl): OAuthFlow
    {
        $this->authorizationUrl = $authorizationUrl;

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

    public function toArray()
    {
        return [
            ...array_filter([
                'authorizationUrl' => $this->authorizationUrl,
                'tokenUrl' => $this->tokenUrl,
                'refreshUrl' => $this->refreshUrl,
            ]),
            // Never filter 'scopes' as it is allowed to be empty. If empty it must be an object
            'scopes' => empty($this->scopes) ? new \stdClass : $this->scopes,
        ];
    }
}
