<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\OffsetUnsetType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\Expr;

class UnsetHandler
{
    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Stmt\Unset_;
    }

    public function leave(Node\Stmt\Unset_ $node, Scope $scope): void
    {
        foreach ($node->vars as $var) {
            if ($var instanceof Node\Expr\ArrayDimFetch) {
                if (! $var->dim) {
                    continue;
                }

                $this->handleArrayKeyUnsetting($node, $var, $scope);
            }
        }
    }

    private function handleArrayKeyUnsetting(Node\Stmt\Unset_ $node, Node\Expr\ArrayDimFetch $targetNode, Scope $scope): void
    {
        /** @var (Expr)[] $path */
        $path = [$targetNode->dim];
        $var = $targetNode->var;

        while ($var instanceof Node\Expr\ArrayDimFetch) {
            if (! $var->dim) {
                return;
            }

            $path = Arr::prepend($path, $var->dim);
            $var = $var->var;
        }

        if (! $var instanceof Node\Expr\Variable) {
            return;
        }

        if (! is_string($var->name)) {
            return;
        }

        $varType = new OffsetUnsetType(
            $scope->getType($var),
            new KeyedArrayType(array_map(
                fn ($pathExpression) => new ArrayItemType_(null, value: $scope->getType($pathExpression)),
                $path,
            )),
        );

        $scope->addVariableType(
            $node->getAttribute('startLine'), // @phpstan-ignore argument.type
            $var->name,
            $varType,
        );

        $scope->setType($node, new VoidType);
    }
}
