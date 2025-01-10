<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Will be removed in 1.0 – Handled by collection extension due to the goal of matching template definitions.
 */
class EloquentCollectionToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && count($type->templateTypes) === 1
            && $type->isInstanceOf(Collection::class)
            && $type->templateTypes[0]->isInstanceOf(Model::class);
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type)
    {
        $type = new ArrayType(value: $type->templateTypes[0]);

        return $this->openApiTransformer->transform($type);
    }
}
