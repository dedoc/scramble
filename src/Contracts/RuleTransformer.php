<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;
use Dedoc\Scramble\Support\NormalizedRule;

interface RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool;

    public function toSchema(Schema $previous, NormalizedRule $rule, RuleTransformerContext $context): Schema;
}
