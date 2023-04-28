<?php

namespace Dedoc\Scramble\Support\Type;

class ObjectType extends AbstractType
{
    public function __construct(
        public string $name,
    ) {
    }

    public function getPropertyFetchType(string $propertyName): Type
    {
        if (array_key_exists($propertyName, $this->properties)) {
            return $this->properties[$propertyName];
        }

        return new UnknownType("Cannot get type of property [$propertyName] on object [$this->name]");
    }

    public function children(): array
    {
        return [
            ...array_values($this->properties),
            ...array_values($this->methods),
        ];
    }

    public function getMethodCallType(string $methodName): Type
    {
        if (! array_key_exists($methodName, $this->methods)) {
            return new UnknownType("Cannot get type of calling method [$methodName] on object [$this->name]");
        }

        return $this->methods[$methodName]->getReturnType();
    }

    public function getMethodType(string $methodName): Type
    {
        if (! array_key_exists($methodName, $this->methods)) {
            return new UnknownType("Cannot get type of method [$methodName] on object [$this->name]");
        }

        return $this->methods[$methodName];
    }

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return $this->name;
    }
}
