<?php

namespace Dedoc\Scramble\Support\Type;

/** @internal */
interface TypeVisitor
{
    public function enter(Type $type): ?Type;

    public function leave(Type $type): ?Type;
}
