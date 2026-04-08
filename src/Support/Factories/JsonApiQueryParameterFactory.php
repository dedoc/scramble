<?php

namespace Dedoc\Scramble\Support\Factories;

use Dedoc\Scramble\Enums\JsonApiArraySerialization;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

class JsonApiQueryParameterFactory
{
    public function __construct(
        private JsonApiArraySerialization $arraySerialization,
    )
    {
    }

    public function createEnumArray(string $name, array $values)
    {
        if ($this->arraySerialization === JsonApiArraySerialization::String) {
            $possibleOptionsDescriptions = array_map(fn ($value) => '`'.$value.'`', $values);

            return Parameter::make($name, 'query')
                ->description('Available options are '.implode(', ', $possibleOptionsDescriptions).'. You can include multiple options by separating them with a comma.')
                ->setSchema(Schema::fromType(new StringType));
        }

        return Parameter::make($name, 'query')
            ->setExplode(false)
            ->setSchema(Schema::fromType(
                (new ArrayType)->setItems((new StringType)->enum($values)),
            ));
    }
}
