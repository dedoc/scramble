<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\PropertyTypeExtension;
use Dedoc\Scramble\Support\Type\EnumCaseType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionEnum;
use UnitEnum;

class EnumPropertyExtension implements PropertyTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return enum_exists($type->name);
    }

    public function getPropertyType(PropertyFetchEvent $event): ?Type
    {
        return match ($event->getName()) {
            'name' => $this->getNamePropertyType($event->getInstance()),
            'value' => $this->getValuePropertyType($event->getInstance()),
            default => null,
        };
    }

    private function getNamePropertyType(ObjectType $instance): Type
    {
        if ($instance instanceof EnumCaseType) {
            return new LiteralStringType($instance->caseName);
        }

        // Return a reference to the enum - names are all possible case names
        return new ObjectType($instance->name);
    }

    private function getValuePropertyType(ObjectType $instance): ?Type
    {
        /** @var class-string<UnitEnum> */
        $enumClass = $instance->name;
        $reflection = new ReflectionEnum($enumClass);

        if (! $reflection->isBacked()) {
            return null;
        }

        if ($instance instanceof EnumCaseType) {
            $case = constant("{$enumClass}::{$instance->caseName}");

            return $this->getLiteralTypeForValue($case->value);
        }

        // Return a reference to the enum - it will be transformed by EnumToSchema
        return new ObjectType($enumClass);
    }

    private function getLiteralTypeForValue(string|int $value): LiteralStringType|LiteralIntegerType
    {
        return is_string($value)
            ? new LiteralStringType($value)
            : new LiteralIntegerType($value);
    }
}
