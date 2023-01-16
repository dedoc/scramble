<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Lang;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
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

        $messageType = $this->getMessageType(1 + $paramsShift, $node, $scope);
        $headersType = TypeHelper::getArgType($scope, $node->args, ['headers', 2 + $paramsShift], new ArrayType());

        return [
            tap(new ObjectType(HttpException::class), function (ObjectType $type) use ($codeType, $messageType, $headersType) {
                $type->properties = array_merge($type->properties, [
                    'statusCode' => $codeType,
                    'message' => $messageType,
                    'headers' => $headersType,
                ]);
            }),
        ];
    }

    private function getMessageType(int $index, Expr $node, Scope $scope)
    {
        $type = TypeHelper::getArgType($scope, $node->args, ['message', $index], new LiteralStringType(''));

        // Message is a string and we should return that message
        if ($type instanceof LiteralStringType) {
            return $type;
        }

        // Extract Raw message argument from the function
        $arg = TypeHelper::getArg($node->args, ['message', $index]);

        // Handle Lang::get(...) / __(...) / trans(...) calls in the message argument
        if ($arg instanceof Arg && $arg->value instanceof Expr\CallLike) {
            $arg = TypeHelper::getArg($arg->value->args, ['key', 0]);
        }

        // If the argument is a string value and is translatable return that as the message type
        if ($arg instanceof Arg && $arg->value instanceof String_ && Lang::has($arg->value->value)) {
            return new LiteralStringType(Lang::get($arg->value->value));
        }

        return $type;
    }
}
