<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Support\Type\ConcatenatedStringType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Stringable;

class TypeToRules
{
    public function evaluate(Type $type): mixed
    {
        if ($type instanceof ConcatenatedStringType) {
            $type = ConcatenatedStringType::fromParts($type->parts);
            if ($type instanceof ConcatenatedStringType) {
                return $this->evaluateConcatenatedRules($type);
            }
        }

        $types = $type instanceof Union ? $type->types : [$type];
        $arrays = array_values(array_filter($types, fn (Type $type) => $type instanceof KeyedArrayType));

        foreach ($arrays as $array) {
            if (! $array->isList) {
                return $this->evaluateRulesMaps($arrays);
            }
        }

        if ($type instanceof Union || $type instanceof KeyedArrayType) {
            return $this->evaluateRuleSet(new Union($types));
        }

        if ($type instanceof NullType) {
            return null;
        }

        if ($type instanceof LiteralType) {
            return $type->getValue();
        }

        return $this->isRule($type) ? new RuleType($type) : null;
    }

    /**
     * @param  KeyedArrayType[]  $maps
     * @return array<string, list<string|RuleType>>
     */
    private function evaluateRulesMaps(array $maps): array
    {
        $fields = [];
        foreach ($maps as $map) {
            foreach ($map->items as $item) {
                $fields[$item->key] = true;
            }
        }

        $result = [];
        foreach (array_keys($fields) as $field) {
            $result[$field] = $this->evaluateRuleSet(new Union(array_map(
                fn (KeyedArrayType $map) => $map->getItemValueTypeByKey($field, new KeyedArrayType),
                $maps,
            )));
        }

        return $result;
    }

    /** @return list<string|RuleType> */
    private function evaluateRuleSet(Type $type, bool $splitString = true): array
    {
        if ($type instanceof ConcatenatedStringType) {
            $type = ConcatenatedStringType::fromParts($type->parts);
            if ($type instanceof ConcatenatedStringType) {
                return $splitString ? $this->evaluateConcatenatedRules($type) : [];
            }
        }

        if ($type instanceof Union) {
            $alternatives = array_map(fn (Type $type) => $this->evaluateRuleSet($type, $splitString), $type->types);
            $required = count($alternatives) > 0;
            $rules = [];

            foreach ($alternatives as $alternative) {
                $required = $required
                    && in_array('required', $alternative, true)
                    && ! in_array('sometimes', $alternative, true);
                array_push($rules, ...$alternative);
            }

            return $this->uniqueRules(array_values(array_filter(
                $rules,
                fn (string|RuleType $rule) => $rule !== 'required' || $required,
            )));
        }

        if ($type instanceof LiteralStringType) {
            return $splitString ? explode('|', $type->value) : [$type->value];
        }

        if ($this->isRule($type)) {
            return [new RuleType($type)];
        }

        $rules = [];
        if ($type instanceof KeyedArrayType) {
            foreach ($type->items as $item) {
                array_push($rules, ...$this->evaluateRuleSet($item->value, splitString: false));
            }
        }

        return $this->uniqueRules($rules);
    }

    private function isRule(Type $type): bool
    {
        return $type instanceof ObjectType
            && ($type->isInstanceOf(Rule::class) || $type->isInstanceOf(ValidationRule::class) || $type->isInstanceOf(Stringable::class));
    }

    /** @return list<string|RuleType> */
    private function evaluateConcatenatedRules(ConcatenatedStringType $type): array
    {
        $segments = [[]];
        foreach ($type->parts as $part) {
            if (! $part instanceof LiteralStringType) {
                $segments[array_key_last($segments)][] = $part;

                continue;
            }

            foreach (explode('|', $part->value) as $index => $value) {
                if ($index > 0) {
                    $segments[] = [];
                }
                if ($value !== '') {
                    $segments[array_key_last($segments)][] = new LiteralStringType($value);
                }
            }
        }

        $rules = [];
        foreach ($segments as $segment) {
            if (! $segment) {
                continue;
            }

            $rule = ConcatenatedStringType::fromParts($segment);
            if ($rule instanceof LiteralStringType || $this->isRule($rule)) {
                array_push($rules, ...$this->evaluateRuleSet($rule));
            }
        }

        return $this->uniqueRules($rules);
    }

    /**
     * @param  list<string|RuleType>  $rules
     * @return list<string|RuleType>
     */
    private function uniqueRules(array $rules): array
    {
        $result = [];
        foreach ($rules as $rule) {
            foreach ($result as $existing) {
                if (
                    $rule === $existing
                    || ($rule instanceof RuleType && $existing instanceof RuleType && $rule->type->isSame($existing->type))
                ) {
                    continue 2;
                }
            }

            $result[] = $rule;
        }

        return $result;
    }
}
