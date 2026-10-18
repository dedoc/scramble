<?php

namespace Dedoc\Scramble\Configuration;

use Illuminate\Support\Arr;

class RendererConfig
{
    public readonly string $view;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param  array{view: string}&array<string, mixed>  $config
     */
    public function __construct(
        array $config
    ) {
        $this->config = Arr::except($config, ['view']);
        $this->view = $config['view'];
    }

    /**
     * @return array<string, array{view: string}&array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'elements' => [
                'view' => 'scramble::docs',
                'theme' => 'light',
                'hideTryIt' => false,
                'hideSchemas' => false,
                'logo' => '',
                'tryItCredentialsPolicy' => 'include',
                'layout' => 'responsive',
                'router' => 'hash',
            ],
            'scalar' => [
                'view' => 'scramble::scalar',
                'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
                'theme' => 'laravel',
                'proxyUrl' => 'https://proxy.scalar.com',
                'darkMode' => false,
                'showDeveloperTools' => 'never',
                'agent' => ['disabled' => true],
                'credentials' => 'include',
            ],
            'fabric' => [
                'view' => 'scramble::fabric',
            ],
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * @param  array<int|string>  $except
     * @return array<string, mixed>
     */
    public function all(array $except = []): array
    {
        return Arr::except($this->config, $except);
    }
}
