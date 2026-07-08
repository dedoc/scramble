<?php

namespace Dedoc\Scramble;

class CacheableGenerator
{
    public function __construct(
        private Generator $generator,
    ) {}

    /**
     * @return array<mixed, mixed>
     */
    public function __invoke(?GeneratorConfig $config = null): array
    {
        $config ??= Scramble::getGeneratorConfig(Scramble::DEFAULT_API);

        $store = config('scramble.cache.store');
        $keyBase = config('scramble.cache.key');

        if (! is_string($store) || ! is_string($keyBase)) {
            return ($this->generator)($config);
        }

        $key = $keyBase.':'.$this->resolveApi($config);

        $cached = cache()->store($store)->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        return ($this->generator)($config);
    }

    private function resolveApi(GeneratorConfig $config): string
    {
        foreach (Scramble::getConfigurationsInstance()->all() as $api => $generatorConfig) {
            if ($generatorConfig === $config) {
                return $api;
            }
        }

        return Scramble::DEFAULT_API;
    }
}
