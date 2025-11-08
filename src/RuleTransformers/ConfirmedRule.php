<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\NormalizedRule;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RuleSetToSchemaTransformer;
use Dedoc\Scramble\Support\SchemaBag;

class ConfirmedRule implements AllRulesSchemasTransformer
{
    public function __construct(
        private RuleSetToSchemaTransformer $rulesToSchemaTransformer,
    ) {}

    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('confirmed');
    }

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void
    {
        $schemaBag->set(
            "{$context->field}_confirmation",
            $this->rulesToSchemaTransformer->transform($context->fieldRules->filter(fn ($r) => $r !== 'confirmed')->all()),
        );
    }
}
