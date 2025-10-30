<?php

namespace Dedoc\Scramble\Contexts;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Support\Collection;

class RuleTransformerContext
{
    public function __construct(
        public readonly OpenApi $openApi,
        public readonly Operation $operation,
        public readonly GeneratorConfig $config,
        public Collection $schemas,
    ) {}
}
