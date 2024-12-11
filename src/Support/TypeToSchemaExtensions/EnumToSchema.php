<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

class EnumToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return function_exists('enum_exists')
            && $type instanceof ObjectType
            && enum_exists($type->name);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        $name = $type->name;

        $values = [];
        $except = $type->hasAttribute('enumExcept') ? $type->getAttribute('enumExcept') : [];

        foreach ($name::cases() as $case) {
            if (!in_array($case, $except, true)) {
                $values[] = $case->value;
            }
        }

        if ($values === []) {
            return new UnknownType("$type->name enum doesnt have values");
        }

        $schemaType = is_string($values[0]) ? new StringType : new IntegerType;
        $schemaType->enum($values);

        return $schemaType;
    }

    public function reference(ObjectType $type)
    {
        return new Reference('schemas', $type->name, $this->components);
    }
}
