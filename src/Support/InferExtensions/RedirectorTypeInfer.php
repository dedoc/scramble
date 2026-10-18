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
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use PhpParser\Node\Expr;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class RedirectorTypeInfer implements ExpressionTypeInferExtension, FunctionReturnTypeExtension, MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType|string $callee): bool
    {
        if (is_string($callee)) {
            return in_array($callee, ['redirect', 'back', 'to_route'], true);
        }

        return $callee->isInstanceOf(Redirector::class);
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        return match ($event->name) {
            'redirect' => $event->getArg('to', 0, new NullType) instanceof NullType
                ? new ObjectType(Redirector::class)
                : (new Generic(RedirectResponse::class, [
                    new LiteralStringType(''),
                    $event->getArg('status', 1, new LiteralIntegerType(302)),
                    $event->getArg('headers', 2, new ArrayType),
                ]))->mergeAttributes(['targetUrl' => new StringType]),
            'back' => (new Generic(RedirectResponse::class, [
                new LiteralStringType(''),
                $event->getArg('status', 0, new LiteralIntegerType(302)),
                $event->getArg('headers', 1, new ArrayType),
            ]))->mergeAttributes(['targetUrl' => new StringType]),
            'to_route' => (new Generic(RedirectResponse::class, [
                new LiteralStringType(''),
                $event->getArg('status', 2, new LiteralIntegerType(302)),
                $event->getArg('headers', 3, new ArrayType),
            ]))->mergeAttributes(['targetUrl' => new StringType]),
            default => null,
        };
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'back', 'refresh' => (new Generic(RedirectResponse::class, [
                new LiteralStringType(''),
                $event->getArg('status', 0, new LiteralIntegerType(302)),
                $event->getArg('headers', 1, new ArrayType),
            ]))->mergeAttributes(['targetUrl' => new StringType]),
            'to', 'secure', 'guest', 'intended' => (new Generic(RedirectResponse::class, [
                new LiteralStringType(''),
                $event->getArg('status', 1, new LiteralIntegerType(302)),
                $event->getArg('headers', 2, new ArrayType),
            ]))->mergeAttributes(['targetUrl' => new StringType]),
            'away' => (new Generic(RedirectResponse::class, [
                new LiteralStringType(''),
                $event->getArg('status', 1, new LiteralIntegerType(302)),
                $event->getArg('headers', 2, new ArrayType),
            ]))->mergeAttributes(['targetUrl' => $event->getArg('path', 0)]),
            'route', 'action' => (new Generic(RedirectResponse::class, [
                new LiteralStringType(''),
                $event->getArg('status', 2, new LiteralIntegerType(302)),
                $event->getArg('headers', 3, new ArrayType),
            ]))->mergeAttributes(['targetUrl' => new StringType]),
            'signedRoute', 'temporarySignedRoute' => (new Generic(RedirectResponse::class, [
                new LiteralStringType(''),
                $event->getArg('status', 3, new LiteralIntegerType(302)),
                $event->getArg('headers', 4, new ArrayType),
            ]))->mergeAttributes(['targetUrl' => new StringType]),
            default => null,
        };
    }

    public function getType(Expr $node, Scope $scope): ?Type
    {
        if (! $node instanceof Expr\New_) {
            return null;
        }

        $type = $scope->getType($node);

        if (
            (! $type instanceof ObjectType && ! $type instanceof NewCallReferenceType)
            || ! is_string($type->name)
            || ! $type->isInstanceOf(SymfonyRedirectResponse::class)
        ) {
            return null;
        }

        return (new Generic($type->name, [
            new LiteralStringType(''),
            TypeHelper::getArgType($scope, $node->args, ['status', 1], new LiteralIntegerType(302)),
            TypeHelper::getArgType($scope, $node->args, ['headers', 2], new ArrayType),
        ]))->mergeAttributes([
            'targetUrl' => TypeHelper::getArgType($scope, $node->args, ['url', 0], new StringType),
        ]);
    }
}
