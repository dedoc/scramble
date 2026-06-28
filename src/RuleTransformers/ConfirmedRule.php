<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;

class ConfirmedRule implements AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('confirmed');
    }

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void
    {
        $explicitConfirmationName = $rule->parameters[0] ?? null;
        $confirmationField = is_string($explicitConfirmationName) ? $explicitConfirmationName : "{$context->field}_confirmation";

        $schemaBag->set(
            $confirmationField,
            clone $schemaBag->getOrFail($context->field),
        );
    }
}
