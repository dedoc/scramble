<?php

namespace Dedoc\Scramble\Support\Type;

/** @internal */
interface TypeVisitor
{
    public const DONT_TRAVERSE_CHILDREN = 1;

    public const DONT_TRAVERSE_CURRENT_AND_CHILDREN = 4;

    public function enter(Type $type): Type|int|null;

    public function leave(Type $type): Type|int|null;
}
