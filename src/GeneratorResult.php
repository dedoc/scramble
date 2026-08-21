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
}
