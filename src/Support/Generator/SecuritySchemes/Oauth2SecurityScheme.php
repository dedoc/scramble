<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\Support\Generator\SecurityScheme;

class Oauth2SecurityScheme extends SecurityScheme
{
    public OAuthFlows $oAuthFlows;

    public function __construct()
    {
        parent::__construct('oauth2');

        $this->oAuthFlows = new OAuthFlows;
    }

    public function flows(callable $flows)
    {
        $flows($this->oAuthFlows);

        return $this;
    }

    public function flow(string $name, callable $flow)
    {
        return $this->flows(function (OAuthFlows $flows) use ($flow, $name) {
            if (! $flows->$name) {
                $flows->$name(new OAuthFlow);
            }
            $flow($flows->$name);
        });
    }

    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'flows' => $this->oAuthFlows->toArray(),
        ]);
    }
}
