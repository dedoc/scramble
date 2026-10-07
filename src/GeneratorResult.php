<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;
use Illuminate\Support\Collection;

class GeneratorResult
{
    public function __construct(
        public OpenApi $openApi,
        /** @var Collection<int, Diagnostic> */
        public Collection $diagnostics,
        public ProNudgeCollector $proNudge,
        public GeneratorConfig $config,
    ) {}

    /** @return array<string, mixed> */
    public function spec(): array
    {
        $openApi = $this->openApi();

        return match ($this->config->openApiVersion()) {
            OpenApiVersion::V3_1 => $openApi->serializeAs31(),
            OpenApiVersion::V3_2 => $openApi->serializeAs32(),
        };
    }

    public function openApi(): OpenApi
    {
        return $this->openApi;
    }

    /** @return Collection<int, Diagnostic> */
    public function diagnostics(): Collection
    {
        return $this->diagnostics;
    }

    public function proNudge(): ProNudgeCollector
    {
        return $this->proNudge;
    }
}
