<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\TypeTransformer;

trait GeneratesParametersFromRules
{
    private function makeParameters($node, $rules, TypeTransformer $typeTransformer, string $in = 'query')
    {
        return (new RulesToParameters($rules, $node, $typeTransformer, $in))->mergeDotNotatedKeys(false)->handle();
    }
}
