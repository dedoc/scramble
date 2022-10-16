<?php

namespace Dedoc\Scramble\Infer\SimpleTypeGetters;

use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node;

class BooleanNotTypeGetter
{
    public function __invoke(Node\Expr\BooleanNot $node): Type
    {
        return new BooleanType;
    }
}
