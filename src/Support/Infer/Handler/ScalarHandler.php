<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;

class ScalarHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Scalar;
    }

    public function leave(Node\Scalar $node)
    {
        if ($node instanceof Node\Scalar\String_) {
            $node->setAttribute('type', new StringType());
            return;
        }

        if ($node instanceof Node\Scalar\LNumber) {
            $node->setAttribute('type', new IntegerType());
            return;
        }
    }
}
