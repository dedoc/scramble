<?php

namespace Dedoc\Scramble\Support\Type;

class ObjectType extends AbstractType
{
    public string $name;

    /**
     * @var array<string, Type>
     */
    public array $properties = [];

    /**
     * @var array<string, FunctionType>
     */
    public array $methods = [];

    public function __construct(
        string $name,
        array $properties = []
    ) {
        $this->name = $name;
        $this->properties = $properties;
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

    public function nodes(): array
    {
        return ['methods', 'properties'];
    }

    public function getMethodCallType(string $methodName): Type
    {
        if (! array_key_exists($methodName, $this->methods)) {
            return new UnknownType("Cannot get type of calling method [$methodName] on object [$this->name]");
        }

        return $this->methods[$methodName]->getReturnType();
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
