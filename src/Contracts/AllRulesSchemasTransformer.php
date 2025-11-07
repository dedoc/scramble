<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Contexts\RuleMappingContext;
use Dedoc\Scramble\Support\NormalizedRule;

interface AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool;

    public function transformAll(array $schemas, NormalizedRule $rule, RuleMappingContext $context): void;
}
