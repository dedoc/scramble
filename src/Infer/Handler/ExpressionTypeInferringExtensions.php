<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class ExpressionTypeInferringExtensions
{
    /** @var ExpressionTypeInferExtension[] */
    private array $extensions;

    public function __construct(array $extensions = [])
    {
        $this->extensions = $extensions;
    }

    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr;
    }

    public function leave(Node\Expr $node, Scope $scope)
    {
        $type = array_reduce(
            $this->extensions,
            function ($acc, ExpressionTypeInferExtension $extension) use ($node, $scope) {
                $type = $extension->getType($node, $scope);
                if ($type) {
                    $scope->setType($node, $type);

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
