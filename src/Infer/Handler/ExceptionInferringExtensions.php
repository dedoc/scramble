<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class ExceptionInferringExtensions
{
    /** @var ExpressionExceptionExtension[] */
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
        if (! $scope->isInFunction()) {
            return;
        }

        $fnType = $scope->function();

        foreach ($this->extensions as $extension) {
            if (! count($exceptions = $extension->getException($node, $scope))) {
                continue;
            }

            $fnType->exceptions = array_merge($fnType->exceptions, $exceptions);
        }
    }
}
