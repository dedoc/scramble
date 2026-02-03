<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\OffsetSetType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\Expr;

class AssignHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\Assign;
    }

    public function leave(Node\Expr\Assign $node, Scope $scope)
    {
        if ($node->var instanceof Node\Expr\Variable) {
            $this->handleVarAssignment($node, $node->var, $scope);
        }

        if ($node->var instanceof Node\Expr\ArrayDimFetch) {
            $this->handleArrayKeyAssignment($node, $node->var, $scope);
        }
    }

    private function handleVarAssignment(Node\Expr\Assign $node, Node\Expr\Variable $var, Scope $scope): void
    {
        $scope->addVariableType(
            $node->getAttribute('startLine'),
            (string) $var->name,
            $type = $scope->getType($node->expr),
        );

        $scope->setType($node, $type);
    }

    private function handleArrayKeyAssignment(Node\Expr\Assign $node, Node\Expr\ArrayDimFetch $targetNode, Scope $scope): void
    {
        /** @var (?Expr)[] $path */
        $path = [$targetNode->dim];
        $var = $targetNode->var;

        while ($var instanceof Node\Expr\ArrayDimFetch) {
            $path = Arr::prepend($path, $var->dim);
            $var = $var->var;
        }

        if (! $var instanceof Node\Expr\Variable) {
            return;
        }

        if (! is_string($var->name)) {
            return;
        }

        $varType = new OffsetSetType(
            $scope->getType($var),
            new KeyedArrayType(array_map(
                fn ($pathExpression) => new ArrayItemType_(null, value: $pathExpression === null ? new TemplatePlaceholderType : $scope->getType($pathExpression)),
                $path,
            )),
            $scope->getType($node->expr),
        );

        $scope->addVariableType(
            $node->getAttribute('startLine'), // @phpstan-ignore argument.type
            $var->name,
            $varType,
        );

        $scope->setType($node, $varType);
    }
}
