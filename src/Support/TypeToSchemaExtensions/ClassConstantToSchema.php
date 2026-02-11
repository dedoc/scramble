<?php
namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Generator\Types as OpenApiType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\ClassConstantType;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;

class ClassConstantToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ClassConstantType;
    }

    /**
     * @param ClassConstantType $type
     */
    public function toSchema(Type $type): OpenApiType\Type
    {
        try {
            $constantValue = $type->constantReflection->getValue();
            $openApiType   = $this->getTypeForConstantValue($constantValue);
            $openApiType->constant($constantValue);
        } catch (\ReflectionException $e) {
            $openApiType = new OpenApiType\UnknownType;
            $openApiType->setDescription("Unable to get type for class constant {$type->toString()}");
        }

        return $openApiType;
    }

    private function getTypeForConstantValue(mixed $constantValue): OpenApiType\Type
    {
        if (is_string($constantValue)) {
            return new OpenApiType\StringType;
        }

        if (is_int($constantValue)) {
            return new OpenApiType\IntegerType;
        }

        if (is_numeric($constantValue)) {
            return new OpenApiType\NumberType;
        }

        if (is_bool($constantValue)) {
            return new OpenApiType\BooleanType;
        }

        return new OpenApiType\UnknownType;
    }
}