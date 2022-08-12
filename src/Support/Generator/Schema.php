<?php

namespace Dedoc\Documentor\Support\Generator;

use Dedoc\Documentor\Support\Generator\Types\ObjectType;
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Dedoc\Documentor\Support\Generator\Types\Type;
use Illuminate\Support\Collection;

class Schema
{
    private Type $type;

    private ?string $title = null;

    private array $enum = [];

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
        $enum = count($this->enum) ? $this->enum : null;

        return array_merge($this->type->toArray(), array_filter([
            'enum' => $enum,
            'title' => $this->title,
        ]));
    }

    public static function createFromParameters(array $parameters)
    {
        $schema = (new static())->setType($type = new ObjectType);

        collect($parameters)
            ->each(function (Parameter $parameter) use ($type) {
                $type->addProperty($parameter->name, $parameter->schema ?? new StringType);
            })
            ->tap(fn (Collection $params) => $type->setRequired(
                $params->where('required', true)->map->name->values()->all()
            ));

        return $schema;
    }

    public function enum(array $enum): Schema
    {
        $this->enum = $enum;

        return $this;
    }

    public function setTitle(?string $title): Schema
    {
        $this->title = $title;

        return $this;
    }
}
