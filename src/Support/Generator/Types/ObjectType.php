<?php

namespace Dedoc\Scramble\Support\Generator\Types;

class ObjectType extends Type
{
    /** @var array<string, Type|null> */
    public array $properties = [];

    /** @var string[] */
    public array $required = [];

    public ?Type $additionalProperties = null;

    public function __construct()
    {
        parent::__construct('object');
    }

    public function addProperty(string $name, $propertyType)
    {
        $this->properties[$name] = $propertyType;

        return $this;
    }

    public function hasProperty(string $name)
    {
        return array_key_exists($name, $this->properties);
    }

    public function getProperty(string $name)
    {
        return $this->properties[$name];
    }

    public function setRequired(array $keys)
    {
        $this->required = $keys;

        return $this;
    }

    public function addRequired(array $keys)
    {
        $this->required = array_merge(
            $this->required,
            array_diff($keys, $this->required),
        );

        return $this;
    }

    public function toArray()
    {
        $result = parent::toArray();

        if (count($this->properties)) {
            $properties = [];
            foreach ($this->properties as $name => $property) {
                $properties[$name] = $property ? $property->toArray() : ['type' => 'string'];
            }
            $result['properties'] = $properties;
        }

        if (count($this->required)) {
            $result['required'] = $this->required;
        }

        if ($this->additionalProperties) {
            $result['additionalProperties'] = $this->additionalProperties->toArray();
        }

        return $result;
    }

    public function additionalProperties(Type $type)
    {
        $this->additionalProperties = $type;

        return $this;
    }
}
