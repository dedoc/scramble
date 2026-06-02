<?php

namespace Dedoc\Scramble\Configuration;

use Illuminate\Support\Arr;

class RendererConfig
{
    public readonly string $view;

    private array $config;

    /**
     * @param  array{view: string}  $config
     */
    public function __construct(
        array $config = []
    ) {
        $this->config = Arr::except($config, ['view']);
        $this->view = $config['view'];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    public function all(array $except = []): array
    {
        return Arr::except($this->config, $except);
    }
}
