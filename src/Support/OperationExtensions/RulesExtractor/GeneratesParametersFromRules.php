<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\RulesNodes;

trait GeneratesParametersFromRules
{
    private function makeParameters(RulesNodes $node, $rules, TypeTransformer $typeTransformer, string $in = 'query')
    {
        return (new RulesToParameters($rules, $node, $typeTransformer, $in))->mergeDotNotatedKeys(false)->handle();
    }
}
