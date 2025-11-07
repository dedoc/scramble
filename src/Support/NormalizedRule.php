<?php

namespace Dedoc\Scramble\Support;

class NormalizedRule
{
    public function __construct(
        public readonly string|object $rule,
        public readonly array $parameters = [],
    )
    {
    }

    public function getRule(): object|string
    {
        return $this->rule;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function is(object|string $rule): bool
    {
        return $this->rule === $rule;
    }

    public function isInstanceOf(string $ruleClass): bool
    {
        return !is_string($this->rule) && is_a(get_class($this->rule), $ruleClass, true);
    }
}
