<?php

namespace Dedoc\Scramble\RuleMappers;

use Dedoc\Scramble\Contexts\RuleMappingContext;
use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\NormalizedRule;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RuleSetToSchemaTransformer;

class ConfirmedRule implements AllRulesSchemasTransformer
{
    public function __construct(
        private RuleSetToSchemaTransformer $rulesToSchemaTransformer,
    )
    {
    }

    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('confirmed');
    }

    public function transformAll(array $schemas, NormalizedRule $rule, RuleMappingContext $context): void
    {
        $schemas->set(
            "{$context->field}_confirmation",
            $this->rulesToSchemaTransformer->transform(array_filter($context->fieldRules, fn ($rule) => $rule !== 'confirmed')),
        );
    }
}
