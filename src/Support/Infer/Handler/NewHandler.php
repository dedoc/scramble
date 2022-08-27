<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Identifier;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;

class NewHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\New_;
    }

    public function leave(Node\Expr\New_ $node)
    {
        if (! ($node->class instanceof Node\Name)) {
            return null;
        }

        $node->setAttribute(
            'type',
            new Identifier($node->class->toString()),
        );
    }
}
