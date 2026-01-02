<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\TypeEffect;
use Dedoc\Scramble\Infer\Scope\ValueFact;
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
        if (! $node->cond instanceof Node\Expr\Variable) {
            return;
        }

        foreach ($node->arms as $arm) {
            if (! $arm->conds) {
                // means other stuff is not matched
                continue;
            }

            foreach ($arm->conds as $cond) {
                $scope->typeEffects->push(new TypeEffect(
                    type: $scope->getType($arm->body),
                    facts: collect([
                        new ValueFact(
                            $node->cond,
                            equals: $scope->getType($cond),
                        )
                    ]),
                ));
            }
        }
    }
}
