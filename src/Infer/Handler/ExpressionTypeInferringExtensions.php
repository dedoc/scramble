<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class ExpressionTypeInferringExtensions implements HandlerInterface
{
    use EnterTrait;

    /** @var ExpressionTypeInferExtension[] */
    private array $extensions;

    public function __construct(array $extensions = [])
    {
        $this->extensions = $extensions;
    }

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Expr;
    }

    public function leave(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

        $type = array_reduce(
            $this->extensions,
            function ($acc, ExpressionTypeInferExtension $extension) use ($node, $scope) {
                $type = $extension->getType($node, $scope);
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
