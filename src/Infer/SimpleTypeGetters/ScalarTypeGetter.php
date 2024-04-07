<?php

namespace Dedoc\Scramble\Infer\SimpleTypeGetters;

use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class ScalarTypeGetter
{
    public function __invoke(Node\Scalar $node): Type
    {
        if ($node instanceof Node\Scalar\String_) {
            return new LiteralStringType($node->value);
        }

        if ($node instanceof Node\Scalar\LNumber) {
            return new LiteralIntegerType($node->value);
        }

        if ($node instanceof Node\Scalar\DNumber) {
            return new LiteralFloatType($node->value);
        }

        return new UnknownType('Cannot get type from scalar');
    }
}
