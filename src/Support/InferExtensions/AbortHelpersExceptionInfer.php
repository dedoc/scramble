<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AbortHelpersExceptionInfer implements ExpressionExceptionExtension
{
    public function getException(Expr $node, Scope $scope): array
    {
        if (! $node instanceof Expr\FuncCall) {
            return [];
        }

        if (! $node->name instanceof Name) {
            return [];
        }

        if (! in_array($node->name->toString(), ['abort', 'abort_if', 'abort_unless'])) {
            return [];
        }

        /*
         * Params for these functions are the same with an exception that `abort` fn doesn't
         * take a first argument. Hence, params shift var.
         */
        $paramsShift = $node->name->toString() === 'abort' ? 0 : 1;

        $codeType = TypeHelper::getArgType($scope, $node->args, ['code', 0 + $paramsShift]);

        if ($codeType instanceof LiteralIntegerType && $codeType->value === 404) {
            return [new ObjectType(ModelNotFoundException::class)];
        }

        $messageType = TypeHelper::getArgType($scope, $node->args, ['message', 1 + $paramsShift], new LiteralStringType(''));
        $headersType = TypeHelper::getArgType($scope, $node->args, ['headers', 2 + $paramsShift], new ArrayType);

        return [
            new Generic(HttpException::class, [
                $codeType,
                $messageType,
                $headersType,
            ]),
        ];
    }
}
