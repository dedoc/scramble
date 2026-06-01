<?php

namespace Dedoc\Scramble\Configuration;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RendererConfig
{
    public function __construct(private array $config = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    public function allCamel(): array
    {
        $camelCasedConfig = [];
        foreach ($this->config as $key => $value) {
            $camelCasedConfig[Str::camel($key)] = $value;
        }

        return $camelCasedConfig;
    }
}
