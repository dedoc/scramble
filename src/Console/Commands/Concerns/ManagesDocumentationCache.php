<?php

namespace Dedoc\Scramble\Console\Commands\Concerns;

use Dedoc\Scramble\Scramble;

trait ManagesDocumentationCache
{
    protected function ensureDocumentationCacheConfigured(): bool
    {
        if (! is_string(config('scramble.cache.store'))) {
            $this->info('Documentation cache store is not configured. Set `scramble.cache.store` in your config.');

            return false;
        }

        if (! is_string(config('scramble.cache.key'))) {
            $this->info('Documentation cache key is not configured. Set `scramble.cache.key` in your config.');

            return false;
        }

        return true;
    }

    /** @return string[] */
    protected function resolveApis(): array
    {
        $apis = array_filter((array) ($this->option('api') ?: []));

        if ($apis === [] || $apis === ['*']) {
            return array_keys(Scramble::getConfigurationsInstance()->all());
        }

        return $apis;
    }

    protected function cacheKey(string $api): string
    {
        $key = config('scramble.cache.key');

        return (is_string($key) ? $key : '').':'.$api;
    }
}
