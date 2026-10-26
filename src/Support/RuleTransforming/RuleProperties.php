<?php

namespace Dedoc\Scramble\Support\RuleTransforming;

use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\RuleType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\EnumCaseType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use ReflectionObject;
use UnitEnum;

/**
 * Reads concrete property values from runtime and inferred rules without coercion.
 * Missing, uninitialized, unknown, or mismatched values return null.
 */
class RuleProperties
{
    public function __construct(
        private readonly string|object $rule,
    ) {}

    public function integer(string $name): ?int
    {
        $value = $this->mixed($name);

        return is_int($value) ? $value : null;
    }

    public function string(string $name): ?string
    {
        $value = $this->mixed($name);

        return is_string($value) ? $value : null;
    }

    public function boolean(string $name): ?bool
    {
        $value = $this->mixed($name);

        return is_bool($value) ? $value : null;
    }

    public function float(string $name): ?float
    {
        $value = $this->mixed($name);

        return is_float($value) ? $value : null;
    }

    /** @return array<array-key, mixed>|null */
    public function array(string $name): ?array
    {
        $value = $this->mixed($name);

        return is_array($value) ? $value : null;
    }

    /**
     * @template TEnum of UnitEnum
     *
     * @param  class-string<TEnum>  $enumClass
     * @return TEnum|null
     */
    public function enum(string $name, string $enumClass): ?UnitEnum
    {
        $value = $this->mixed($name);

        return $value instanceof UnitEnum && $value instanceof $enumClass ? $value : null;
    }

    public function type(string $name): ?Type
    {
        if ($this->rule instanceof RuleType) {
            return $this->rule->type instanceof ObjectType
                ? $this->rule->type->getPropertyType($name)
                : null;
        }

        return TypeHelper::createTypeFromValue($this->mixed($name));
    }

    public function mixed(string $name): mixed
    {
        if ($this->rule instanceof RuleType) {
            if (! $this->rule->type instanceof ObjectType) {
                return null;
            }

            $resolved = true;
            $value = $this->resolve($this->rule->type->getPropertyType($name), $resolved);

            return $resolved ? $value : null;
        }

        if (! is_object($this->rule)) {
            return null;
        }

        $reflection = new ReflectionObject($this->rule);
        do {
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);

                return $property->isInitialized($this->rule) ? $property->getValue($this->rule) : null;
            }
        } while ($reflection = $reflection->getParentClass());

        return null;
    }

    private function resolve(Type $type, bool &$resolved): mixed
    {
        if ($type instanceof LiteralType) {
            return $type->getValue();
        }

        if ($type instanceof NullType) {
            return null;
        }

        if ($type instanceof EnumCaseType && enum_exists($type->name)) {
            $case = $type->name.'::'.$type->caseName;
            if (defined($case)) {
                $value = constant($case);
                if ($value instanceof UnitEnum) {
                    return $value;
                }
            }
        }

        if ($type instanceof KeyedArrayType) {
            $values = [];
            foreach ($type->items as $item) {
                if ($item->isOptional || $item->shouldUnpack) {
                    $resolved = false;

                    return null;
                }

                $key = $item->key;
                if ($item->keyType !== null) {
                    $key = $this->resolve($item->keyType, $resolved);
                    if (! $resolved || (! is_int($key) && ! is_string($key))) {
                        $resolved = false;

                        return null;
                    }
                }

                $value = $this->resolve($item->value, $resolved);
                if (! $resolved) {
                    return null;
                }

                if ($key === null) {
                    $values[] = $value;
                } else {
                    $values[$key] = $value;
                }
            }

            return $values;
        }

        $resolved = false;

        return null;
    }
}
