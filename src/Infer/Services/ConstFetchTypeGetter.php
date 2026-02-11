<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ClassConstantType;
use Dedoc\Scramble\Support\Type\EnumCaseType;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;

class ConstFetchTypeGetter
{
    public function __invoke(Scope $scope, string $className, string $constName): Type
    {
        if ($constName === 'class') {
            return new GenericClassStringType(new ObjectType($className));
        }

        try {
            $constantReflection = new \ReflectionClassConstant($className, $constName); // @phpstan-ignore argument.type
            $constantValue = $constantReflection->getValue();

            if ($constantReflection->isEnumCase()) {
                return new EnumCaseType($className, $constName);
            }

            $classConstantsAsConst = config('scramble.class_constants_as_const');
            if ($classConstantsAsConst) {
                return new ClassConstantType($constantReflection);
            }

            $type = TypeHelper::createTypeFromValue($constantValue);
            if ($type) {
                return $type;
            }
        } catch (\ReflectionException $e) {
            return new UnknownType('Cannot get const value');
        }

        return new UnknownType('ConstFetchTypeGetter is not yet implemented fully for non-class const fetches.');
    }
}
