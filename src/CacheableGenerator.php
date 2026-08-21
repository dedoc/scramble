<?php

namespace Dedoc\Scramble;

class CacheableGenerator
{
    const CACHE_VERSION = 2;

    public function __construct(
        private Generator $generator,
    ) {}

    public function __invoke(?GeneratorConfig $config = null): array
    {
        return $this
            ->generate($config ?? Scramble::getGeneratorConfig(Scramble::DEFAULT_API))
            ->openApi
            ->toArray();
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
        $key = static::cacheKey($config);

        // @todo move to constructor so self cannot be created without them?
        if (! is_string($store) || ! is_string($key)) {
            return null;
        }

        return $this->getValidCachedResult($store, $key);
    }

    private function getValidCachedResult(string $store, string $key): ?GeneratorResult
    {
        if (! $cachedPayload = cache()->store($store)->get($key)) {
            return null;
        }

        if (! is_array($cachedPayload)) {
            return null;
        }

        if (
            ! array_key_exists('_version', $cachedPayload)
            || ! array_key_exists('payload', $cachedPayload)
        ) {
            return null;
        }

        if ($cachedPayload['_version'] !== static::CACHE_VERSION) {
            return null;
        }

        return $cachedPayload['payload'] instanceof GeneratorResult
            ? $cachedPayload['payload']
            : null;
    }

    /**
     * @internal
     */
    public static function store(GeneratorConfig $config, GeneratorResult $result): void
    {
        $store = config('scramble.cache.store');
        $key = static::cacheKey($config);

        if (! is_string($store) || ! is_string($key)) {
            return;
        }

        cache()->store($store)->forever($key, static::prepareCachePayload($result));
    }

    /**
     * @internal
     */
    public static function forget(GeneratorConfig $config): void
    {
        $store = config('scramble.cache.store');
        $key = static::cacheKey($config);

        if (! is_string($store) || ! is_string($key)) {
            return;
        }

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

    /**
     * @return array{version: integer, payload: GeneratorResult}
     */
    private static function prepareCachePayload(GeneratorResult $result): array
    {
        return [
            '_version' => static::CACHE_VERSION,
            'payload' => $result,
        ];
    }
}
