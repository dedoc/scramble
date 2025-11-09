<?php

namespace Dedoc\Scramble\RuleTransformers;

use BackedEnum;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
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

        $objectType = new ObjectType($enumName);

        /** @var BackedEnum[] $except */
        $except = method_exists(Enum::class, 'except') ? $this->getProtectedValue($rule, 'except') : []; // @phpstan-ignore function.alreadyNarrowedType
        /** @var BackedEnum[] $only */
        $only = method_exists(Enum::class, 'only') ? $this->getProtectedValue($rule, 'only') : []; // @phpstan-ignore function.alreadyNarrowedType

        if ($except || $only) {
            return $this->createPartialEnum($enumName, $only, $except);
        }

        return $this->openApiTransformer->transform($objectType);
    }

    /**
     * @param  class-string<BackedEnum>  $enumName
     * @param  BackedEnum[]  $only
     * @param  BackedEnum[]  $except
     */
    private function createPartialEnum(string $enumName, array $only, array $except): Type
    {
        $cases = collect($enumName::cases())
            ->reject(fn ($case) => in_array($case, $except))
            ->filter(fn ($case) => ! $only || in_array($case, $only));

        if (! isset($cases->first()->value)) {
            return new UnknownType; // $enumName enum doesnt have values (only/except context)
        }

        return $this->openApiTransformer->transform(Union::wrap(
            $cases->map(fn ($c) => TypeHelper::createTypeFromValue($c->value))->all()
        ));
    }

    private function getProtectedValue(object $obj, string $name): mixed
    {
        $array = (array) $obj;
        $prefix = chr(0).'*'.chr(0);

        return $array[$prefix.$name];
    }
}
