<?php

namespace Dedoc\Scramble\Support\InferExtensions\Concerns;

use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;

trait CreatesRequestInputArrays
{
    protected function maybeMarkAsRequestInput(Type $instance, Type $returnType): Type
    {
        if ($instance->getAttribute('mayContainRequestInput') === true) {
            $returnType->setAttribute('mayContainRequestInput', true);
        }

        return $returnType;
    }

    protected function buildArrayType(): Type
    {
        return new ArrayType(value: new MixedType, key: new Union([
            new IntegerType,
            new StringType,
        ]));
    }
}
