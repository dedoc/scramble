<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NeverType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;

trait BuildsEnumDefinition
{
    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function buildEnumDefinition(ReflectionClass $reflection, ClassDefinition $definition): void
    {
        if (! enum_exists($name = $reflection->getName())) {
            return;
        }

        $enum = new ReflectionEnum($name);
        $names = [];
        $values = [];

        foreach ($enum->getCases() as $case) {
            $names[] = new LiteralStringType($case->getName());
            $value = $case instanceof ReflectionEnumBackedCase
                ? TypeHelper::createTypeFromValue($case->getBackingValue())
                : null;
            $definition->enumCases[$case->getName()] = $value;

            if ($value) {
                $values[] = $value;
            }
        }

        $definition->properties['name'] = new ClassPropertyDefinition(
            type: count($names) ? Union::wrap($names) : new NeverType,
        );

        if ($enum->isBacked()) {
            $definition->properties['value'] = new ClassPropertyDefinition(
                type: count($values) ? Union::wrap($values) : new NeverType,
            );
        }
    }
}
