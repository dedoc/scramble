<?php
namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionClassConstant;

class ClassConstantType extends ObjectType
{
    public function __construct(
        public ReflectionClassConstant $constantReflection
    ) {
        parent::__construct($constantReflection->getName());
    }

    public function isSame(Type $type): bool
    {
        return $type instanceof static && $type->toString() === $this->toString();
    }

    public function toString(): string
    {
        $classString = $this->constantReflection->getDeclaringClass()->getName();
        $constName = $this->constantReflection->getName();

        return "{$classString}::{$constName}";
    }
}
