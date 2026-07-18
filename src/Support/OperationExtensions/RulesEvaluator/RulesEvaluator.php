<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Exceptions\RulesEvaluationException;

interface RulesEvaluator
{
    /**
     * @return array<string, RuleSet>
     *
     * @throws RulesEvaluationException
     */
    public function handle(): array;
}
