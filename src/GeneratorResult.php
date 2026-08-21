<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;

class GeneratorResult
{
    public function __construct(
        public OpenApi $openApi,
        public DiagnosticsCollector $diagnostics,
        public ProNudgeCollector $proNudge,
    )
    {
    }
}
