<?php

namespace Dedoc\Scramble\Infer\SimpleTypeGetters;

use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class CastTypeGetter
{
    public function __invoke(Node\Expr\Cast $node): Type
    {
        if ($node instanceof Node\Expr\Cast\Int_) {
            return new IntegerType;
        }

        if ($node instanceof Node\Expr\Cast\String_) {
            return new StringType;
        }

        if ($node instanceof Node\Expr\Cast\Bool_) {
            return new BooleanType;
        }

        if ($node instanceof Node\Expr\Cast\Double) {
            return new FloatType; // @todo: not really, there are real, double, float
        }

        return new UnknownType('Cannot get type from cast');
    }
}
