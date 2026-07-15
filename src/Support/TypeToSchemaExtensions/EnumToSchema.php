<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\EnumTransformer;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types as OpenApi;
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
    public function toSchema(Type $type): OpenApi\Type
    {
        return EnumTransformer::make($type->name)->transform();
    }

    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
