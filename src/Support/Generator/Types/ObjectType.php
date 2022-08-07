<?php

namespace Dedoc\Documentor\Support\Generator\Types;

class ObjectType extends Type
{
    /** @var array<string, Type|null> */
    private array $properties = [];

    /** @var string[] */
    private array $required = [];

    public function __construct()
    {
        parent::__construct('object');
    }

    public function addProperty(string $name, $propertyType)
    {
        $this->properties[$name] = $propertyType;

        return $this;
    }

    public function setRequired(array $keys)
    {
        $this->required = $keys;

        return $this;
    }

    public function toArray()
    {
        $result = parent::toArray();

        $properties = [];
        foreach ($this->properties as $name => $property) {
            $properties[$name] = $property ? $property->toArray() : ['type' => 'string'];
        }
        $result['properties'] = $properties;

        if (count($this->required)) {
            $result['required'] = $this->required;
        }

        return $result;
    }
}
