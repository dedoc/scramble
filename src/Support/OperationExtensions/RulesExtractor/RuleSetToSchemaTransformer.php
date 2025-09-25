<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * Transforms a set of validation rules to JSON schema. "Rule set" is a set of rules that are applied to the same
 * piece of data. For example, when writing request validation, every attribute is associated with a rule set:
 * ```php
 * $request->validate(['foo' => ['required', 'boolean']]);
 * ```
 * In this example, the rule set is `['required', 'boolean']`.
 * Rules are processed in order defined in `static::RULES_PRIORITY` – this is needed so the type defining rules are
 * processed first.
 */
class RuleSetToSchemaTransformer
{
    private const RULES_PRIORITY = [
        'bool', 'boolean', 'numeric', 'int', 'integer', 'file', 'image', 'string', 'array', 'exists',
    ];

    private const IGNORE_STRINGABLE_RULES = [
        RequiredIf::class,
        ExcludeIf::class,
        ProhibitedIf::class,
    ];

    public function __construct(
        private TypeTransformer $openApiTransformer,
    ) {}

    /**
     * @param  RuleSet  $rules
     */
    public function transform(mixed $rules, OpenApiType $initialType = new UnknownType): OpenApiType
    {
        $rules = $this->normalizeAndPrioritizeRules($rules);

        $result = $this->transformToSchema($rules, $initialType);

        $isRequired = $this->checkIfRequired($rules);
        if ($isRequired !== null) {
            $result->setAttribute('required', $isRequired);
        }

        return $result;
    }

    /**
     * @param  Collection<int, Rule>  $rules
     */
    protected function transformToSchema(Collection $rules, OpenApiType $initialType): OpenApiType
    {
        return $rules->reduce(function (OpenApiType $type, $rule) {
            if (is_string($rule)) {
                return $this->transformStringRuleToSchema($type, $rule);
            }

            return method_exists($rule, 'docs')
                ? $rule->docs($type, $this->openApiTransformer)
                : $this->transformRuleValueToSchema($type, $rule);
        }, $initialType);
    }

    protected function transformStringRuleToSchema(OpenApiType $type, string $rule): OpenApiType
    {
        $rulesHandler = new RulesMapper($this->openApiTransformer, $this);

        $explodedRule = explode(':', $rule, 2);

        $ruleName = $explodedRule[0];
        $params = isset($explodedRule[1]) ? explode(',', $explodedRule[1]) : [];

        return method_exists($rulesHandler, $ruleName)
            ? $rulesHandler->$ruleName($type, $params)
            : $type;
    }

    /**
     * @param  object  $rule
     */
    protected function transformRuleValueToSchema(OpenApiType $type, $rule): OpenApiType
    {
        $rulesHandler = new RulesMapper($this->openApiTransformer, $this);

        $methodName = Str::camel(class_basename(get_class($rule)));

        return method_exists($rulesHandler, $methodName)
            ? $rulesHandler->$methodName($type, $rule)
            : $type;
    }

    /**
     * @param  Collection<int, Rule>  $rules
     */
    protected function checkIfRequired(Collection $rules): ?bool
    {
        if ($rules->containsStrict('sometimes')) {
            return false;
        }

        if ($rules->containsStrict('required') || $rules->containsStrict('present')) {
            return true;
        }

        return null;
    }

    /**
     * @param  RuleSet  $rules
     * @return Collection<int, Rule>
     */
    protected function normalizeAndPrioritizeRules(mixed $rules): Collection
    {
        $normalizedRules = Arr::wrap(is_string($rules) ? explode('|', $rules) : $rules);

        return collect($normalizedRules)
            ->map(function ($rule) {
                if (is_string($rule)) {
                    return $rule;
                }

                if (in_array($rule::class, self::IGNORE_STRINGABLE_RULES)) {
                    return $rule;
                }

                if (! method_exists($rule, '__toString')) {
                    return $rule;
                }

                try {
                    return $rule->__toString();
                } catch (\Throwable) {
                    return $rule;
                }
            })
            ->sortByDesc($this->rulesSorter(...));
    }

    /**
     * @param  Rule  $rule
     */
    protected function rulesSorter($rule): int
    {
        if (! is_string($rule)) {
            return -2;
        }

        $index = array_search($rule, self::RULES_PRIORITY);

        return $index === false ? -1 : count(self::RULES_PRIORITY) - $index;
    }
}
