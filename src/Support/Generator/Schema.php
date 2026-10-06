<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Collection;
use JsonSerializable;

class Schema implements JsonSerializable, OpenApiSerializable
{
    public Type $type;

    private ?string $title = null;

    public static function fromType(Type $type)
    {
        $schema = new static;
        $schema->setType($type);

        return $schema;
    }

    private function setType(Type $type)
    {
        $this->type = $type;

        return $this;
    }

    public static function createFromParameters(array $parameters)
    {
        $schema = (new static)->setType($type = new ObjectType);

        collect($parameters)
            ->each(function (Parameter $parameter) use ($type) {
                $paramType = $parameter->schema ?? new StringType;
                $paramType = $paramType instanceof Schema ? $paramType->type : $paramType;

                $paramType->setDescription($parameter->description);
                $paramType->examples([$parameter->example]);

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

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(callable $serializeItem): mixed
    {
        $typeArray = $serializeItem($this->type);

        if ($typeArray instanceof \stdClass) { // mixed
            $typeArray = [];
        }

        $result = array_merge($typeArray, array_filter([
            'title' => $this->title,
        ]));

        return $result ?: (object) [];
    }
}
