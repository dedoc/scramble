<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

class ResponseFactoryTypeInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        // call to response()
        if (
            $node instanceof Expr\FuncCall
            && $node->name instanceof Name && $node->name->toString() === 'response'
        ) {
            if (count($node->args)) {
                // Response
            }

            return new ObjectType(ResponseFactory::class);
        }

        // call to a method on the response factory
        if (
            $node instanceof Expr\MethodCall
            && $scope->getType($node->var)->isInstanceOf(ResponseFactory::class)
        ) {
            if ($node->name instanceof Identifier && $node->name == 'noContent') {
                // Response 204, no content
                return new Generic(
                    new ObjectType(Response::class),
                    [
                        new LiteralStringType(''),
                        $this->getArgType($scope, $node->args, ['status', 0], new LiteralIntegerType(204)),
                        $this->getArgType($scope, $node->args, ['headers', 1], new ArrayType),
                    ],
                );
            }

            if ($node->name instanceof Identifier && $node->name == 'json') {
                return new Generic(
                    new ObjectType(JsonResponse::class),
                    [
                        $this->getArgType($scope, $node->args, ['data', 0], new ArrayType),
                        $this->getArgType($scope, $node->args, ['status', 1], new LiteralIntegerType(200)),
                        $this->getArgType($scope, $node->args, ['headers', 2], new ArrayType),
                    ],
                );
            }

            if ($node->name instanceof Identifier && $node->name == 'make') {
                return new Generic(
                    new ObjectType(Response::class),
                    [
                        $this->getArgType($scope, $node->args, ['content', 0], new LiteralStringType('')),
                        $this->getArgType($scope, $node->args, ['status', 1], new LiteralIntegerType(200)),
                        $this->getArgType($scope, $node->args, ['headers', 2], new ArrayType),
                    ],
                );
            }
        }

        return null;
    }

    private function getArgType(Scope $scope, array $args, array $parameterNameIndex, Type $default)
    {
        $matchingArg = $this->getArg($args, $parameterNameIndex);

        return $matchingArg ? $scope->getType($matchingArg->value) : $default;
    }

    private function getArg(array $args, array $parameterNameIndex)
    {
        [$name, $index] = $parameterNameIndex;

        return collect($args)->first(
            fn ($arg) => ($arg->name->name ?? '') === $name,
            fn () => empty($args[$index]->name->name) ? ($args[$index] ?? null) : null,
        );
    }
}
