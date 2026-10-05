<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
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
            'flows' => $serializeItem($this->oAuthFlows),
        ]);
    }
}
