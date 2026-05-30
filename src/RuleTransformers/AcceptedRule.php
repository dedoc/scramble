<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;

class AcceptedRule implements RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('accepted');
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        return $previous->enum(['yes', 'on', '1', 'true']);
    }
}
