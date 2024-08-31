<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Http\Response;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

class RedirectHelpersInfer implements ExpressionExceptionExtension
{
    public function getException(Expr $node, Scope $scope): array
    {
        if (! $node instanceof Expr\FuncCall) {
            return [];
        }

        if (! $node->name instanceof Name) {
            return [];
        }

        if (! in_array($node->name->toString(), ['redirect'])) {
            return [];
        }

        return [
            new Generic(
                Response::class,
                [
                    TypeHelper::getArgType($scope, $node->args, ['to', 0], new LiteralStringType('')),
                    TypeHelper::getArgType($scope, $node->args, ['status', 1], new LiteralIntegerType(302)),
                    TypeHelper::getArgType($scope, $node->args, ['headers', 2], new ArrayType),
                ],
            ),
        ];
    }
}
