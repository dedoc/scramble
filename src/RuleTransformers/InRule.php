<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Contracts\StringRuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Stringable;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

class InRule implements StringRuleTransformer
{
    public function shouldHandle(string $rule): bool
    {
        return $rule === 'in';
    }

    public function toSchema(Schema $previous, string $rule, array $params): Schema
    {
        return $previous->enum(
            collect($params)
                ->mapInto(Stringable::class)
                ->map(fn (Stringable $v) => (string) $v->trim('"')->replace('""', '"'))
                ->values()
                ->all()
        );
    }
}
