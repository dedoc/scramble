<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Support\Type\AbstractTypeVisitor;
use Dedoc\Scramble\Support\Type\ConcatenatedStringType;
use Dedoc\Scramble\Support\Type\Type;

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
