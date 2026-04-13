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
    ) {}

    /**
     * @param (string|int)[] $values
     */
    public function createEnumArray(string $name, array $values): Parameter
    {
        if ($this->arraySerialization === JsonApiArraySerialization::String) {
            $possibleOptionsDescriptions = array_map(fn ($value) => '`'.$value.'`', $values);

            return Parameter::make($name, 'query')
                ->description('Available values are '.implode(', ', $possibleOptionsDescriptions).'. You can include multiple values by separating them with a comma.')
                ->setSchema(Schema::fromType(new StringType));
        }

        return tap(
            Parameter::make($name, 'query')
                ->setExplode(false)
                ->setSchema(Schema::fromType(
                    (new ArrayType)->setItems((new StringType)->enum($values)),
                )),
            /** Setting `isFlat` prevents QueryParametersConverter to append `[]` to parameter {@see QueryParametersConverter::shouldAdaptParameterForQuery} */
            fn (Parameter $p) => $p->setAttribute('isFlat', true),
        );
    }
}
