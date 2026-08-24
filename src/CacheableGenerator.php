<?php

namespace Dedoc\Scramble;

class CacheableGenerator
{
    const CACHE_VERSION = 2;

    public function __construct(
        private Generator $generator,
    ) {}

    /** @return array<mixed, mixed> */
    public function __invoke(?GeneratorConfig $config = null): array
    {
        return $this
            ->generate($config ?? Scramble::getGeneratorConfig(Scramble::DEFAULT_API))
            ->spec();
    }

    public function generate(GeneratorConfig $config): GeneratorResult
    {
        if ($cachedResult = $this->getCachedResult($config)) {
            return $cachedResult;
        }

        return $this->generator->generate($config);
    }

    private function getCachedResult(GeneratorConfig $config): ?GeneratorResult
    {
        $store = config('scramble.cache.store');
        $key = self::cacheKey($config);

        if (! is_string($store) || ! is_string($key)) {
            return null;
        }

        return $this->getValidCachedResult($store, $key);
    }

    private function getValidCachedResult(string $store, string $key): ?GeneratorResult
    {
        $cachedResult = rescue(
            fn () => cache()->store($store)->get($key),
            report: false,
        );

        // Older Scramble versions cached the specification array directly and
        // did not store a version. Keep serving it so upgrades remain seamless.
        if (is_array($cachedResult)) {
            return new OldGeneratorResult($cachedResult);
        }

        if (cache()->store($store)->get(self::versionKey($key)) !== static::CACHE_VERSION) {
            return null;
        }

        if (! $cachedResult) {
            return null;
        }

        return $cachedResult instanceof GeneratorResult
            ? $cachedResult
            : null;
    }

    /**
     * @internal
     */
    public static function store(GeneratorConfig $config, GeneratorResult $result): void
    {
        $store = config('scramble.cache.store');
        $key = self::cacheKey($config);

        if (! is_string($store) || ! is_string($key)) {
            return;
        }

        cache()->store($store)->forever($key, $result);
        cache()->store($store)->forever(self::versionKey($key), static::CACHE_VERSION);
    }

    /**
     * @internal
     */
    public static function forget(GeneratorConfig $config): void
    {
        $store = config('scramble.cache.store');
        $key = self::cacheKey($config);

        if (! is_string($store) || ! is_string($key)) {
            return;
        }

        cache()->store($store)->forget(self::versionKey($key));
        cache()->store($store)->forget($key);
    }

    private static function cacheKey(GeneratorConfig $config): ?string
    {
        $key = config('scramble.cache.key');

        if (! is_string($key)) {
            return null;
        }

        $key .= ':'.$config->name;

        return $key;
    }

    private static function versionKey(string $key): string
    {
        return $key.':_version';
    }
}
