<?php

namespace Dedoc\Scramble\RuleMappers;

use Dedoc\Scramble\Contexts\RuleMappingContext;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;
use Dedoc\Scramble\Support\NormalizedRule;
use Illuminate\Support\Stringable;

class InRule implements RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('in');
    }

    public function toSchema(Schema $previous, NormalizedRule $rule, RuleMappingContext $context): Schema
    {
        return $previous->enum(
            collect($rule->parameters)
                ->mapInto(Stringable::class)
                ->map(fn (Stringable $v) => (string) $v->trim('"')->replace('""', '"'))
                ->values()
                ->all()
        );
    }
}
