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

        if (config('scramble.cache.store') === null || config('scramble.cache.key') === null) {
            return ($this->generator)($config);
        }

        $store = config()->string('scramble.cache.store');
        $keyBase = config()->string('scramble.cache.key');

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
