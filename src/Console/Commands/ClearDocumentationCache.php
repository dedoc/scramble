<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Console\Commands\Concerns\ManagesDocumentationCache;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;

class ClearDocumentationCache extends Command
{
    use ManagesDocumentationCache;

    protected $signature = 'scramble:clear
        {--api=* : The API to clear a documentation cache for (by default, clears all APIs)}
    ';

    protected $description = 'Clear the cached OpenAPI document.';

    public function handle(): int
    {
        if (! $this->ensureDocumentationCacheConfigured()) {
            return self::SUCCESS;
        }

        foreach ($this->resolveApis() as $api) {
            CacheableGenerator::forget(Scramble::getGeneratorConfig($api));

            $this->info("OpenAPI document cache cleared for [{$api}] API.");
        }

        return self::SUCCESS;
    }
}
