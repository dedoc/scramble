<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\OffsetAccessType;
use Dedoc\Scramble\Support\Type\OffsetSetType;
use Dedoc\Scramble\Support\Type\Reference\PropertyAssignReferenceType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\Type;
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

        if ($node->var instanceof Node\Expr\Array_ || $node->var instanceof Node\Expr\List_) {
            $this->handleDestructuringAssignment($node, $node->var, $scope);
        }

        if ($node->var instanceof Node\Expr\PropertyFetch) {
            $this->handlePropertyAssignment($node, $node->var, $scope);
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

    private function handleDestructuringAssignment(
        Node\Expr\Assign $node,
        Node\Expr\Array_|Node\Expr\List_ $pattern,
        Scope $scope,
    ): void {
        $rhsType = $scope->getType($node->expr);
        $line = $node->getAttribute('startLine');

        $this->assignFromPattern($rhsType, $pattern, $line, $scope);

        $scope->setType($node, $rhsType);
    }

    private function assignFromPattern(
        Type $sourceType,
        Node\Expr\Array_|Node\Expr\List_ $pattern,
        int $line,
        Scope $scope,
    ): void {
        $position = 0;

        foreach ($pattern->items as $item) {
            if (! $item) {
                $position++;

                continue;
            }

            if ($item->unpack) {
                if ($item->key === null) {
                    $position++;
                }

                continue;
            }

            $offsetType = $item->key === null
                ? new LiteralIntegerType($position)
                : $scope->getType($item->key);

            $elementType = new OffsetAccessType($sourceType, $offsetType);

            if ($item->value instanceof Node\Expr\Variable && is_string($item->value->name)) {
                $scope->addVariableType($line, $item->value->name, $elementType);
            } elseif ($item->value instanceof Node\Expr\Array_ || $item->value instanceof Node\Expr\List_) {
                $this->assignFromPattern($elementType, $item->value, $line, $scope);
            }

            if ($item->key === null) {
                $position++;
            }
        }
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

    private function handlePropertyAssignment(Node\Expr\Assign $node, Node\Expr\PropertyFetch $targetNode, Scope $scope): void
    {
        if (! $targetNode->var instanceof Node\Expr\Variable || ! is_string($targetNode->var->name)) {
            return;
        }

        if (! $targetNode->name instanceof Node\Identifier) {
            return;
        }

        $varType = new PropertyAssignReferenceType(
            $scope->getType($targetNode->var),
            $targetNode->name->name,
            $scope->getType($node->expr),
        );

        $scope->addVariableType(
            $node->getAttribute('startLine'),
            $targetNode->var->name,
            $varType,
        );

        $scope->setType($node, $varType);
    }
}
