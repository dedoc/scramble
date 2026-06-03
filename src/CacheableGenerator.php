<?php

namespace Dedoc\Scramble;

class CacheableGenerator
{
    public function __construct(
        private Generator $generator,
    ) {}

    public function __invoke(?GeneratorConfig $config = null): array
    {
        $config ??= Scramble::getGeneratorConfig(Scramble::DEFAULT_API);

        $store = config('scramble.cache.store', 'file');
        $key = config('scramble.cache.key').':'.$this->resolveApi($config);

        if ($cached = cache()->store($store)->get($key)) {
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
