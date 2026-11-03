<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

class RuleStaticMethodReturnTypeExtension implements StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === Rule::class;
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'in' => new Generic(In::class, [
                new LiteralStringType('in'),
                new Generic(InRuleValuesResolver::class, [
                    $event->getArg('values', 0),
                    new KeyedArrayType(array_map(fn (Type $type) => new ArrayItemType_(null, $type), array_values($event->arguments->all()))),
                ]),
            ]),
            'enum' => new Generic(Enum::class, [
                $event->getArg('type', 0),
                new NullType,
                new KeyedArrayType,
                new KeyedArrayType,
            ]),
            default => null,
        };
    }
}
