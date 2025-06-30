<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class InferredParameter
{
    /**
     * @param  array<array-key, scalar>|scalar|null|MissingValue  $default
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public Type $type = new UnknownType,
        public mixed $default = new MissingValue,
    ) {}

    public function toOpenApiParameter(TypeTransformer $transformer): Parameter
    {
        $parameter = Parameter::make($this->name, 'query'/* @todo: this is just a temp solution */)
            ->description($this->description ?: '')
            ->setSchema(Schema::fromType(
                $transformer
                    ->transform($this->type)
                    ->default($this->default)
            ));

        if ($this->type->getAttribute('isFlat')) {
            $parameter->setAttribute('isFlat', true);
        }

        if ($this->type->getAttribute('isInQuery')) {
            $parameter->setAttribute('isInQuery', true);
        }

        return $parameter;
    }
}
