<?php

namespace Dedoc\Scramble\Infer\SimpleTypeGetters;

use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class ConstFetchTypeGetter
{
    public function __invoke(Node\Expr\ConstFetch $node): Type
    {
        if ($node->name->toString() === 'null') {
            return new NullType;
        }

        if ($node->name->toString() === 'true') {
            return new LiteralBooleanType(true);
        }

        if ($node->name->toString() === 'false') {
            return new LiteralBooleanType(false);
        }

        return new UnknownType('Cannot get type from constant fetch');
    }
}
