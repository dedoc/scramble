<?php

namespace Dedoc\Scramble\RuleTransformers;

use BackedEnum;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Support\Stringable;
use Illuminate\Validation\Rules\In;
use UnitEnum;

class InRule implements RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('in') || $rule->instanceOf(In::class);
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        if ($rule->instanceOf(In::class)) {
            $values = $rule->properties()->array('values');

            if ($values === null) {
                return $previous;
            }

            $enum = [];
            foreach ($values as $value) {
                $value = $value instanceof BackedEnum ? $value->value : ($value instanceof UnitEnum ? $value->name : $value);
                if (! is_scalar($value) && $value !== null) {
                    return $previous;
                }

                $enum[] = (string) $value;
            }
        } else {
            $enum = collect($rule->getParameters())
                ->mapInto(Stringable::class)
                ->map(fn (Stringable $v) => (string) $v->trim('"')->replace('""', '"'))
                ->values()
                ->all();
        }

        if ($previous instanceof ArrayType) {
            $previous->items->enum($enum);

            return $previous;
        }

        return $previous->enum($enum);
    }
}
