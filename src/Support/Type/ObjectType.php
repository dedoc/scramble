<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;

class ObjectType extends AbstractType
{
    public function __construct(
        public string $name,
    ) {
    }

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function getPropertyType(string $propertyName, Scope $scope): Type
    {
        $className = $this::class;

        return new UnknownType("Cannot get a property type [$propertyName] on type [{$className}]");
    }

    public function getMethodDefinition(string $methodName, Scope $scope = new GlobalScope): ?FunctionLikeDefinition
    {
        $classDefinition = $scope->index->getClassDefinition($this->name);

        if (! $classDefinition || ! array_key_exists($methodName, $classDefinition->methods)) {
            return null;
        }

        return $classDefinition->methods[$methodName];
    }

    public function toString(): string
    {
        return $this->name;
    }
}
