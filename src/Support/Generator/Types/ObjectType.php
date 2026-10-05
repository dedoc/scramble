<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;

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

    public function clone(): static
    {
        $clone = parent::clone();

        foreach ($clone->properties as $name => $property) {
            $clone->properties[$name] = $property?->clone();
        }

        if ($clone->additionalProperties) {
            $clone->additionalProperties = $clone->additionalProperties->clone();
        }

        return $clone;
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

    public function additionalProperties(Type $type)
    {
        $this->additionalProperties = $type;

        return $this;
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(parent::serializeAs31(), fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(parent::serializeAs32(), fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(array $parentArray, callable $serializeItem): mixed
    {
        $result = $parentArray;

        if (count($this->properties)) {
            $properties = [];
            foreach ($this->properties as $name => $property) {
                $properties[$name] = $property ? $serializeItem($property) : ['type' => 'string'];
            }
            $result['properties'] = $properties;
        }

        if (count($this->required)) {
            $result['required'] = $this->required;
        }

        if ($this->additionalProperties) {
            $result['additionalProperties'] = $serializeItem($this->additionalProperties);
        }

        return $result;
    }
}
