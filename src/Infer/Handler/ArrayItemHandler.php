<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\ConstFetchEvaluator;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;

class ArrayItemHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\ArrayItem;
    }

    public function leave(Node\Expr\ArrayItem $node, Scope $scope)
    {
        $keyType = $node->key ? $scope->getType($node->key) : null;

        $scope->setType(
            $node,
            new ArrayItemType_(
                $this->evaluateKeyNode($node->key, $scope), // @todo handle cases when key is something dynamic
                $scope->getType($node->value),
                isOptional: false,
                shouldUnpack: $node->unpack,
                keyType: $keyType,
            )
        );
    }

    private function evaluateKeyNode(?Node\Expr $key, Scope $scope): int|string|null
    {
        if (! $key) {
            return null;
        }

        $evaluator = new ConstExprEvaluator(function (Node\Expr $node) use ($scope) {
            if ($node instanceof Node\Expr\ClassConstFetch) {
                return (new ConstFetchEvaluator([
                    'self' => $scope->classDefinition()?->name,
                    'static' => $scope->classDefinition()?->name,
                ]))->evaluate($node);
            }

            return null;
        });

        try {
            $result = $evaluator->evaluateSilently($key);

            return is_string($result) || is_int($result) ? $result : null;
        } catch (ConstExprEvaluationException) {
            return null;
        }
    }
}
