<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Support\Type\Type;

class RuleType
{
    public function __construct(
        public readonly Type $type,
    )
    {
    }
}
