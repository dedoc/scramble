<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Exceptions\RulesEvaluationException;

interface RulesEvaluator
{
    /**
     * @throws RulesEvaluationException
     * @return array<string, RuleSet>
     */
    public function handle(): array;
}
