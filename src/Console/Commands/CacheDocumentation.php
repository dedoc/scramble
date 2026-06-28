<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Concerns\ManagesDocumentationCache;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;

class CacheDocumentation extends Command
{
    use ManagesDocumentationCache;

    protected $signature = 'scramble:cache
        {--api=* : The API to cache a documentation for (by default, caches all APIs)}
    ';

    protected $description = 'Cache the generated OpenAPI document.';

    public function handle(Generator $generator): int
    {
        if (! $this->ensureDocumentationCacheConfigured()) {
            return self::SUCCESS;
        }

        $store = config('scramble.cache.store');

        foreach ($this->resolveApis() as $api) {
            $config = Scramble::getGeneratorConfig($api);

            cache()->store($store)->forever(
                $this->cacheKey($api),
                $generator($config),
            );

            $this->info("OpenAPI document cached for [{$api}] API.");
        }

        return self::SUCCESS;
    }
}
