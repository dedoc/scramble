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

class ConcatenatedStringInliningTypeVisitor extends AbstractTypeVisitor
{
    public function leave(Type $type): ?Type
    {
        if (! $type instanceof ConcatenatedStringType) {
            return null;
        }

        return ConcatenatedStringType::fromParts($type->parts);
    }
}
