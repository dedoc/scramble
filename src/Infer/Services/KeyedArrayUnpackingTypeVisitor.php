<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Support\Type\AbstractTypeVisitor;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;

class KeyedArrayUnpackingTypeVisitor extends AbstractTypeVisitor
{
    public function leave(Type $type): ?Type
    {
        if (! $type instanceof KeyedArrayType) {
            return null;
        }

        return TypeHelper::unpackIfArray($type);
    }
}
