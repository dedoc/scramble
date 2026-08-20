<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;

class CacheableGenerator
{
    private DiagnosticsCollector $diagnostics;

    private ProNudgeCollector $proNudge;

    public function __construct(
        private Generator $generator,
    ) {
        $this->diagnostics = new DiagnosticsCollector;
        $this->proNudge = new ProNudgeCollector;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function __invoke(?GeneratorConfig $config = null): array
    {
        $this->diagnostics = new DiagnosticsCollector;
        $this->proNudge = new ProNudgeCollector;

        $config ??= Scramble::getGeneratorConfig(Scramble::DEFAULT_API);

        $store = config('scramble.cache.store');
        $keyBase = config('scramble.cache.key');

        if (! is_string($store) || ! is_string($keyBase)) {
            return $this->generate($config);
        }

        $key = $keyBase.':'.$config->name;

        $cached = cache()->store($store)->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        return $this->generate($config);
    }

    public function diagnostics(): DiagnosticsCollector
    {
        return $this->diagnostics;
    }

    public function proNudge(): ProNudgeCollector
    {
        return $this->proNudge;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function generate(GeneratorConfig $config): array
    {
        $spec = ($this->generator)($config);
        $this->diagnostics = $this->generator->diagnostics;
        $this->proNudge = $this->generator->proNudge;

        return $spec;
    }
}
