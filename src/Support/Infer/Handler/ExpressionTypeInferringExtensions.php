<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use PhpParser\Node;

class ExpressionTypeInferringExtensions
{
    private array $extensions;

    public function __construct(array $extensions = [])
    {
        $this->extensions = $extensions;
    }

    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr;
    }

    public function leave(Node $node, Scope $scope)
    {
        $type = array_reduce(
            $this->extensions,
            function ($acc, $extensionClass) use ($node, $scope) {
                $type = (new $extensionClass)->getExpressionType($node, $scope);
                if ($type) {
                    return $type;
                }

                return $acc;
            },
        );

        if ($type) {
            $scope->setType($node, $type);
        }
    }
}
