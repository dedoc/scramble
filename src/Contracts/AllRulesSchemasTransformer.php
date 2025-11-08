<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\Support\NormalizedRule;
use Dedoc\Scramble\Support\SchemaBag;

interface AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool;

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void;
}
