<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\ConstFetchEvaluator;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;

class MatchHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\Match_;
    }

    public function leave(Node\Expr\Match_ $node, Scope $scope)
    {

    }
}
