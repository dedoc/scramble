<?php

namespace Dedoc\Scramble\Support\RuleTransforming;

/**
 * @template-covariant TRule of string|object = string|object
 */
class NormalizedRule
{
    /**
     * @param  TRule  $rule
     * @param  list<mixed>  $parameters
     */
    public function __construct(
        public readonly string|object $rule,
        public readonly array $parameters = [],
    ) {}

    /**
     * @template TRuleParam of string|object
     *
     * @param  TRuleParam  $rule
     * @return ($rule is string ? self<string> : self<TRuleParam>)
     */
    public static function fromValue(string|object $rule): self
    {
        if (is_string($rule)) {
            $explodedRule = explode(':', $rule, 2);

            $ruleName = $explodedRule[0];
            $params = isset($explodedRule[1]) ? explode(',', $explodedRule[1]) : [];

            return new NormalizedRule($ruleName, $params);
        }

        return new NormalizedRule($rule);
    }

    /**
     * @return TRule
     */
    public function getRule(): object|string
    {
        return $this->rule;
    }

    /**
     * @return list<mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function is(object|string $rule): bool
    {
        if (is_string($this->rule)) {
            return $this->rule === $rule;
        }

        return is_string($rule) && is_a(get_class($this->rule), $rule, true);
    }
}
