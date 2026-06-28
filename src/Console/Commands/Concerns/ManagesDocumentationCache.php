<?php

namespace Dedoc\Scramble\Console\Commands\Concerns;

use Dedoc\Scramble\Scramble;

trait ManagesDocumentationCache
{
    protected function ensureDocumentationCacheConfigured(): bool
    {
        if (config('scramble.cache.store') === null) {
            $this->info('Documentation cache store is not configured. Set `scramble.cache.store` in your config.');

            return false;
        }

        if (config('scramble.cache.key') === null) {
            $this->info('Documentation cache key is not configured. Set `scramble.cache.key` in your config.');

            return false;
        }

        return true;
    }

    /** @return string[] */
    protected function resolveApis(): array
    {
        $apis = array_filter($this->option('api') ?: []);

        if ($apis === [] || $apis === ['*']) {
            return array_keys(Scramble::getConfigurationsInstance()->all());
        }

        return $apis;
    }

    protected function cacheKey(string $api): string
    {
        return config('scramble.cache.key').':'.$api;
    }
}
