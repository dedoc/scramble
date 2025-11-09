<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;

interface RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool;

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type;
}
