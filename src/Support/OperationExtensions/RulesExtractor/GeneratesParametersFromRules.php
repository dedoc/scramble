<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

trait GeneratesParametersFromRules
{
    /**
     * @param  array<string, RuleSet>  $rules
     * @param  array<string, PhpDocNode>  $rulesDocs
     * @return Parameter[]
     */
    private function makeParameters($rules, TypeTransformer $typeTransformer, array $rulesDocs = [], string $in = 'query'): array
    {
        return (new RulesToParameters($rules, $typeTransformer, $rulesDocs, $in))->mergeDotNotatedKeys(false)->handle();
    }
}
