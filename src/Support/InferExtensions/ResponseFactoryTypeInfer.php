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
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use League\MimeTypeDetection\GeneratedExtensionToMimeTypeMap;
use PhpParser\Node\Expr;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            return new Generic(
                Response::class,
                [
                    $event->getArg('content', 0, new LiteralStringType('')),
                    $event->getArg('status', 1, new LiteralIntegerType(200)),
                    $event->getArg('headers', 2, new ArrayType),
                ],
            );
        }
        return new ObjectType(ResponseFactory::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match($event->name) {
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
            'download' => $this->generateBinaryResponseForDownloadCall($event),
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
            $contentName = $scope->getType($node)->isInstanceOf(JsonResponse::class) ? 'data' : 'content';
            $contentDefaultType = $scope->getType($node)->isInstanceOf(JsonResponse::class)
                ? new ArrayType
                : new LiteralStringType('');

            return new Generic($scope->getType($node)->name, [
                TypeHelper::getArgType($scope, $node->args, [$contentName, 0], $contentDefaultType),
                TypeHelper::getArgType($scope, $node->args, ['status', 1], new LiteralIntegerType(200)),
                TypeHelper::getArgType($scope, $node->args, ['headers', 2], new ArrayType),
            ]);
        }

        return null;
    }

    private function generateBinaryResponseForDownloadCall(MethodCallEvent $event): Generic
    {
        $mimeType = 'application/octet-stream';

        $fileArgumentType = $event->getArg('file', 0, new LiteralStringType(''));

        $fileName = $this->guessFileNameFromType($fileArgumentType);
        if ($fileName && class_exists(ExtensionMimeTypeDetector::class)) {
            $mimeType = (new ExtensionMimeTypeDetector)->detectMimeTypeFromPath($fileName);
        }

        // based on the file name (including the name override), make the content disposition
        $overridingFileName = $this->guessFileNameFromType($event->getArg('name', 1, new NullType));

        $contentDispositionArgumentType = $event->getArg('disposition', 3, new LiteralStringType('attachment'));
        $contentDisposition = $contentDispositionArgumentType instanceof LiteralStringType ? $contentDispositionArgumentType->value : 'inline';
        if ($contentDisposition === 'attachment') {
            $contentDisposition = $this->getContentDispositionAttachmentHeader(
                $fileName,
                $overridingFileName,
            );
        }

        // if content-type header is passed, use it! @todo

        // if nothing has worked, use `application/octet-stream` as a mime type

        // store determined type in the type attribute

        $responseType = new Generic(BinaryFileResponse::class, [
            $fileArgumentType,
            new LiteralIntegerType(200),
            $event->getArg('headers', 2, new ArrayType),
            $contentDispositionArgumentType,
        ]);

        $responseType->setAttribute('mimeType', $mimeType);
        $responseType->setAttribute('contentDisposition', $contentDisposition);

        dd($responseType);

        return $responseType;
    }

    private function guessFileNameFromType(Type $fileArgumentType): ?string
    {
        $stringLiterals = (new TypeWalker)->findAll($fileArgumentType, fn (Type $t) => $t instanceof LiteralStringType);

        foreach (array_reverse($stringLiterals) as $stringLiteral) {
            if (! $stringLiteral instanceof LiteralStringType) {
                continue;
            }

            if ($this->isFileName($stringLiteral->value)) {
                return $stringLiteral->value;
            }
        }

        return null;
    }

    private function isFileName(string $str): bool
    {
        return (bool) preg_match('/^[^.].*\.[^.]+$/', $str);
    }

    private function getContentDispositionAttachmentHeader(?string $fileName, ?string $overridingFileName): string
    {
        if (! $fileName && ! $overridingFileName) {
            return 'attachment';
        }

        $name = $overridingFileName ?: $fileName;

        return 'attachment; filename='.$name;
    }
}
