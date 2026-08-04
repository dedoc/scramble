<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\DeepParametersMerger;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;

/**
 * The `distinct` rule is applied to the items of an array (`foo.*`, `foo.*.id`), while `uniqueItems` documents
 * the array itself (`foo`), hence the containing array's schema is the one being transformed here.
 */
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

        $schema = $schemaBag->get($arrayField);

        $arraySchema = match (true) {
            $schema === null => new ArrayType,
            $schema instanceof ArrayType => $schema,
            $schema instanceof UnknownType => (new ArrayType)->addProperties($schema),
            default => null,
        };

        if (! $arraySchema instanceof ArrayType) {
            return;
        }

        $schemaBag->set($arrayField, $arraySchema->setUniqueItems());
    }

    /**
     * Gets the name of the array containing the items the rule is applied to: `foo.*.id` results in `foo`.
     */
    private function getContainingArrayField(string $field): ?string
    {
        $parts = preg_split(DeepParametersMerger::DOT_REGEX, $field) ?: [$field];

        $wildcardKeys = array_keys($parts, '*', true);

        $lastWildcardKey = end($wildcardKeys);

        // The rule is either not applied to array items, or applied to the items of the root
        // array, which is not documented as a separate schema.
        if (! is_int($lastWildcardKey) || $lastWildcardKey === 0) {
            return null;
        }

        return implode('.', array_slice($parts, 0, $lastWildcardKey));
    }
}
