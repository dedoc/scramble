<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Collection;

class CollectionToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && count($type->templateTypes) === 2
            && $type->isInstanceOf(Collection::class);
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type)
    {
        $type = new ArrayType(value: $type->templateTypes[1]/* TValue */);

        return $this->openApiTransformer->transform($type);
    }
}
