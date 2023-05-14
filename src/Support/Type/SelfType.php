<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;

class SelfType extends ObjectType
{
    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'self';
    }
}
