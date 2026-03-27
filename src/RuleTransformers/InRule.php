<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Support\Stringable;

class InRule implements RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('in');
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        $enum = collect($rule->parameters)
            ->mapInto(Stringable::class)
            ->map(fn (Stringable $v) => (string) $v->trim('"')->replace('""', '"'))
            ->values()
            ->all();

        if ($previous instanceof ArrayType) {
            $previous->items->enum($enum);

            return $previous;
        }

        return $previous->enum($enum);
    }
}
