<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Exceptions\RulesEvaluationException;

interface RulesEvaluator
{
    /** @throws RulesEvaluationException */
    public function handle(): array;
}
