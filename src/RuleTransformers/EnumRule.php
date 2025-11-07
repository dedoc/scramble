<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\NormalizedRule;
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
        return $rule->isInstanceOf(Enum::class);
    }

    public function toSchema(Schema $previous, NormalizedRule $rule, RuleTransformerContext $context): Schema
    {
        $rule = $rule->getRule();

        $enumName = $this->getProtectedValue($rule, 'type');

        $objectType = new ObjectType($enumName);

        $except = method_exists(Enum::class, 'except') ? $this->getProtectedValue($rule, 'except') : [];
        $only = method_exists(Enum::class, 'only') ? $this->getProtectedValue($rule, 'only') : [];

        if ($except || $only) {
            return $this->createPartialEnum($enumName, $only, $except);
        }

        return $this->openApiTransformer->transform($objectType);
    }

    /**
     * @param  class-string<object>  $enumName
     * @param  (string|int)[]  $only
     * @param  (string|int)[]  $except
     */
    private function createPartialEnum(string $enumName, array $only, array $except): Schema
    {
        $cases = collect($enumName::cases())
            ->reject(fn ($case) => in_array($case, $except))
            ->filter(fn ($case) => ! $only || in_array($case, $only));

        if (! isset($cases->first()?->value)) {
            return new UnknownType("$enumName enum doesnt have values (only/except context)");
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
