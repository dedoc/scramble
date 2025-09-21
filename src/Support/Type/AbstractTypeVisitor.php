<?php

namespace Dedoc\Scramble\Support\Type;

abstract class AbstractTypeVisitor implements TypeVisitor
{
    public function enter(Type $type): Type|int|null
    {
        return null;
    }

    public function leave(Type $type): Type|int|null
    {
        return null;
    }
}
