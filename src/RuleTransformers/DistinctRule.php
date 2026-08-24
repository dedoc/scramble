<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\DeepParametersMerger;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use Illuminate\Support\Str;

class DistinctRule implements AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('distinct');
    }

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void
    {
        if (! $arrayField = $this->getContainingArrayField($context->field)) {
            return;
        }

        $schema = $schemaBag->get($arrayField) ?? new ArrayType;

        if ($schema instanceof UnknownType) {
            $schema = (new ArrayType)->addProperties($schema);
        }

        if (! $schema instanceof ArrayType) {
            return;
        }

        $schemaBag->set($arrayField, $schema->setUniqueItems(true));
    }

    private function getContainingArrayField(string $field): ?string
    {
        $parts = Str::of($field)->split(DeepParametersMerger::DOT_REGEX);

        if (! $lastWildcardKey = $parts->reverse()->search('*')) {
            return null;
        }

        return $parts->take($lastWildcardKey)->join('.');
    }
}
