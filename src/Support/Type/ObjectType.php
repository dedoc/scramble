<?php

namespace Dedoc\Scramble\Support\Type;

class ObjectType extends AbstractType
{
    public string $name;

    /**
     * @var array<string, Type>
     */
    public array $properties = [];

    public function __construct(
        string $name,
        array $properties = []
    )
    {
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
