<?php

namespace Dedoc\Scramble\Contexts;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Illuminate\Support\Collection;

class RuleMappingContext
{
    public function __construct(
        public readonly string $field,
        public readonly array $fieldRules,
        public readonly OpenApi $openApi,
        public readonly GeneratorConfig $config,
    ) {}
}
