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
    ) {}

    /** @return array<string, mixed> */
    public function spec(OpenApiVersion $version = OpenApiVersion::V3_1): array
    {
        $openApi = $this->openApi();

        return match ($version) {
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
