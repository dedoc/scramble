<?php

namespace Dedoc\Scramble\Support\Type;

interface FunctionLikeType extends Type
{
    public function setReturnType(Type $type): self;

    public function getReturnType(): Type;
}
