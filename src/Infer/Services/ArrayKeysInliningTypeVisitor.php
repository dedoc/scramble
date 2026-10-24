<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Support\Type\AbstractTypeVisitor;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ConcatenatedStringType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;

class ArrayKeysInliningTypeVisitor extends AbstractTypeVisitor
{
    public function leave(Type $type): ?Type
    {
        if (! $type instanceof ArrayItemType_) {
            return null;
        }

        if (! $keyType = $type->keyType) {
            return null;
        }

        $keyType = $keyType instanceof ConcatenatedStringType
            ? ConcatenatedStringType::fromParts($keyType->parts)
            : $keyType;

        if (
            !$keyType instanceof LiteralIntegerType
            && !$keyType instanceof LiteralStringType
        ) {
            return null;
        }

        $type->key = $keyType->getValue();

        return $type;
    }
}
