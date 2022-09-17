<?php

namespace Dedoc\Scramble\Infer\SimpleTypeGetters;

use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class ScalarTypeGetter
{
    public function __invoke(Node\Scalar $node): Type
    {
        if ($node instanceof Node\Scalar\String_) {
            return new StringType();
        }

        if ($node instanceof Node\Scalar\LNumber) {
            return new IntegerType();
        }

        return new UnknownType('Cannot get type from scalar');
    }
}
