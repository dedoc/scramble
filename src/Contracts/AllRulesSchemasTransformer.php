<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;

interface AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool;

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void;
}
