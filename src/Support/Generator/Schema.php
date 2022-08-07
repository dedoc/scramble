<?php

namespace Dedoc\Documentor\Support\Generator;

use Dedoc\Documentor\Support\Generator\Types\BooleanType;
use Dedoc\Documentor\Support\Generator\Types\ObjectType;
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Dedoc\Documentor\Support\Generator\Types\Type;
use Illuminate\Support\Collection;

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

    public static function createFromParameters(array $parameters)
    {
        $schema = (new static())->setType($type = new ObjectType);

        collect($parameters)
            ->each(function (Parameter $parameter) use ($type) {
                $type->addProperty($parameter->name, $parameter->schema->type ?? new StringType);
            })
            ->tap(fn (Collection $params) => $type->setRequired(
                $params->where('required', true)->map->name->values()->all()
            ));

        return $schema;
    }
}
