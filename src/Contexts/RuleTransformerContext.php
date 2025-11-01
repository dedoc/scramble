<?php

namespace Dedoc\Scramble\Contexts;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Support\Collection;

class RuleTransformerContext
{
    public function __construct(
        public readonly OpenApi $openApi,
        public readonly Operation $operation,
        public readonly GeneratorConfig $config,
        public readonly TypeTransformer $openApiTransformer,
    ) {}
}
