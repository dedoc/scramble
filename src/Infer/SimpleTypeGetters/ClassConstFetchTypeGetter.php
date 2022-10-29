<?php

namespace Dedoc\Scramble\Infer\SimpleTypeGetters;

use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class ClassConstFetchTypeGetter
{
    public function __invoke(Node\Expr\ClassConstFetch $node): Type
    {
        if ($node->name->toString() === 'class') {
            return new LiteralStringType($node->class->toString());
        }

        return new UnknownType('Cannot get type from class const fetch');
    }
}
