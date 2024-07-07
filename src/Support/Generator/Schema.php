<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Collection;

class Schema
{
    public Type $type;

    private ?string $title = null;

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
        return array_merge($this->type->toArray(), array_filter([
            'title' => $this->title,
        ]));
    }

    public static function createFromParameters(array $parameters)
    {
        $schema = (new static())->setType($type = new ObjectType);

        collect($parameters)
            ->each(function (Parameter $parameter) use ($type) {
                $paramType = $parameter->schema ?? new StringType;
                $paramType = $paramType instanceof Schema ? $paramType->type : $paramType;

                $paramType->setDescription($parameter->description);
                $paramType->example($parameter->example);

                $type->addProperty($parameter->name, $paramType);
            })
            ->tap(fn (Collection $params) => $type->setRequired(
                $params->where('required', true)->map->name->values()->all()
            ));

        return $schema;
    }

    public function setTitle(?string $title): Schema
    {
        $this->title = $title;

        return $this;
    }
}
