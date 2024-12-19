<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

class EnumToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return function_exists('enum_exists')
            && $type instanceof ObjectType
            && enum_exists($type->name);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): UnknownType|IntegerType|StringType
    {
        $enum = $type->name;

        if (! isset($enum::cases()[0]->value)) {
            return new UnknownType("$type->name enum doesnt have values");
        }

        $values = array_map(static fn ($s) => $s->value, $enum::cases());

        $schemaType = is_string($values[0])
            ? new StringType
            : new IntegerType;

        return $schemaType->enum($values);
    }

    public function reference(ObjectType $type): Reference
    {
        $enum = $type->name;

        $phpDocString = ClassReflector::make($enum)
            ->getReflection()
            ->getDocComment();

        $fullName = SchemaClassDocReflector::createFromDocString($phpDocString)->getSchemaName($enum);

        return new Reference('schemas', $fullName, $this->components);
    }
}
