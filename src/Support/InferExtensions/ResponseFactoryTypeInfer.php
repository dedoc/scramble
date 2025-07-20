<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Extensions\FunctionReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PhpParser\Node\Expr;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseFactoryTypeInfer implements ExpressionTypeInferExtension, FunctionReturnTypeExtension, MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType|string $callee): bool
    {
        if (is_string($callee)) {
            return $callee === 'response';
        }

        return $callee->isInstanceOf(ResponseFactory::class);
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        if (count($event->arguments)) {
            return new Generic(Response::class, [
                $event->getArg('content', 0, new LiteralStringType('')),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]);
        }

        return new ObjectType(ResponseFactory::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'noContent' => new Generic(Response::class, [
                new LiteralStringType(''),
                $event->getArg('status', 0, new LiteralIntegerType(204)),
                $event->getArg('headers', 1, new ArrayType),
            ]),
            'json' => new Generic(JsonResponse::class, [
                $event->getArg('data', 0, new ArrayType),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'make' => new Generic(Response::class, [
                $event->getArg('content', 0, new LiteralStringType('')),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'download' => (new BinaryFileResponseTypeFactory(
                file: $event->getArg('file', 0),
                name: $event->getArg('name', 1, new NullType),
                headers: $event->getArg('headers', 2, new ArrayType),
                disposition: $event->getArg('disposition', 3, new LiteralStringType('attachment')),
            ))->build(),
            'file' => (new BinaryFileResponseTypeFactory(
                file: $event->getArg('file', 0),
                headers: $event->getArg('headers', 1, new ArrayType),
            ))->build(),
            'stream' => new Generic(StreamedResponse::class, [
                $event->getArg('callbackOrChunks', 0),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'streamJson' => new Generic(StreamedJsonResponse::class, [
                $event->getArg('data', 0),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'streamDownload' => new Generic(StreamedResponse::class, [
                $event->getArg('callback', 0),
                new LiteralIntegerType(200),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'eventStream' => (new Generic(StreamedResponse::class, [
                $event->getArg('callback', 0),
                new LiteralIntegerType(200),
                $event->getArg('headers', 1, new ArrayType),
            ]))->mergeAttributes([
                'mimeType' => 'text/event-stream',
                'endStreamWith' => ($endStreamWithType = $event->getArg('endStreamWith', 2, new LiteralStringType('</stream>'))) instanceof LiteralStringType
                    ? $endStreamWithType->value
                    : null,
            ]),
            default => null,
        };
    }

    public function getType(Expr $node, Scope $scope): ?Type
    {
        // call Response and JsonResponse constructors
        if (
            $node instanceof Expr\New_
            && (
                $scope->getType($node)->isInstanceOf(JsonResponse::class)
                || $scope->getType($node)->isInstanceOf(Response::class)
            )
        ) {
            /** @var ObjectType $nodeType */
            $nodeType = $scope->getType($node);

            $contentName = $nodeType->isInstanceOf(JsonResponse::class) ? 'data' : 'content';
            $contentDefaultType = $nodeType->isInstanceOf(JsonResponse::class)
                ? new ArrayType
                : new LiteralStringType('');

            return new Generic($nodeType->name, [
                TypeHelper::getArgType($scope, $node->args, [$contentName, 0], $contentDefaultType),
                TypeHelper::getArgType($scope, $node->args, ['status', 1], new LiteralIntegerType(200)),
                TypeHelper::getArgType($scope, $node->args, ['headers', 2], new ArrayType),
            ]);
        }

        return null;
    }
}
