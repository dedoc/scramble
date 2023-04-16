<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use PhpParser\Node;

class ArrayHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\Array_;
    }

    public function leave(Node\Expr\Array_ $node, Scope $scope)
    {
        $arrayItems = collect($node->items)
            ->filter()
            ->flatMap(function (Node\Expr\ArrayItem $arrayItem) use ($scope) {
                /** @var ArrayItemType_ $type */
                $type = $scope->getType($arrayItem);

                if ($type->shouldUnpack && $type->value instanceof ArrayType) {
                    return $type->value->items;
                }

                if ($type->shouldUnpack && ! $type->value instanceof ArrayType) {
                    return [];
                }

                return [$type];
            })
            ->reduce(function ($arrayItems, ArrayItemType_ $itemType) {
                if (! $itemType->key) {
                    $arrayItems[] = $itemType;
                } else {
                    $arrayItems[$itemType->key] = $itemType;
                }

                return $arrayItems;
            }, []);

        $scope->setType($node, new ArrayType(array_values($arrayItems)));
    }
}
