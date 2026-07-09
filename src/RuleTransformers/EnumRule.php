<?php

namespace Dedoc\Scramble\RuleTransformers;

use BackedEnum;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\EnumTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\Type\ObjectType;
use Illuminate\Validation\Rules\Enum;

class EnumRule implements RuleTransformer
{
    public function __construct(
        protected TypeTransformer $openApiTransformer,
    ) {}

    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is(Enum::class);
    }

    /**
     * @param  NormalizedRule<Enum>  $rule
     */
    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        $rule = $rule->getRule();

        /** @var class-string<BackedEnum> $enumName */
        $enumName = $this->getProtectedValue($rule, 'type');

        /** @var BackedEnum[] $except */
        $except = method_exists(Enum::class, 'except') ? $this->getProtectedValue($rule, 'except') : []; // @phpstan-ignore function.alreadyNarrowedType
        /** @var BackedEnum[] $only */
        $only = method_exists(Enum::class, 'only') ? $this->getProtectedValue($rule, 'only') : []; // @phpstan-ignore function.alreadyNarrowedType

        if ($except || $only) {
            return $this->preservePreviousRules(
                EnumTransformer::make($enumName)
                    ->except($except)
                    ->only($only)
                    ->transform(),
                $previous,
            );
        }

        return $this->preservePreviousRules(
            $this->openApiTransformer->transform(new ObjectType($enumName)),
            $previous,
        );
    }

    private function preservePreviousRules(Type $current, Type $previous): Type
    {
        if ($previous->nullable) {
            $current->nullable(true);
        }

        return $current;
    }

    private function getProtectedValue(object $obj, string $name): mixed
    {
        $array = (array) $obj;
        $prefix = chr(0).'*'.chr(0);

        return $array[$prefix.$name];
    }
}
