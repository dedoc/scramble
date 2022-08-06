<?php

namespace Dedoc\Documentor\Support\Generator;

use Dedoc\Documentor\Support\Generator\Types\BooleanType;
use Dedoc\Documentor\Support\Generator\Types\ObjectType;
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Dedoc\Documentor\Support\Generator\Types\Type;

class Schema
{
    private Type $type;

    public static function createFromArray(array $request)
    {
        $schema = new static();

        $schema->setType(
            $type = new ObjectType
        );
        foreach ($request as $name => $rules) {
            $propertyType = null;

            if (in_array('string', $rules)) {
                $propertyType = new StringType;
            } elseif (in_array('bool', $rules)) {
                $propertyType = new BooleanType;
            }

            $type->addProperty($name, $propertyType);
        }
        $type->setRequired(
            collect($request)->filter(fn (array $rules) => in_array('required', $rules))->keys()->toArray()
        );

        return $schema;
    }

    public static function fromType(Type $type)
    {
        $schema = new static();
        $schema->setType($type);
        return $schema;
    }

    private function setType(Type $type)
    {
        $this->type = $type;
        return $this;
    }

    public function toArray()
    {
        return $this->type->toArray();
    }
}
