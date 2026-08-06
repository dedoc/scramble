<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\Concerns\GuessesMimeTypeFromFileType;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageResponseTypeInfer implements MethodReturnTypeExtension
{
    use GuessesMimeTypeFromFileType;

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(FilesystemManager::class)
            || $type->isInstanceOf(Filesystem::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        $arguments = match ($event->name) {
            'download', 'response' => [
                $event->getArg('path', 0),
                $event->getArg('name', 1, new NullType),
                $event->getArg('headers', 2, new ArrayType),
            ],
            'serve' => [
                $event->getArg('path', 1),
                $event->getArg('name', 2, new NullType),
                $event->getArg('headers', 3, new ArrayType),
            ],
            default => null,
        };

        if (! $arguments) {
            return null;
        }

        [$file, $name, $headers] = $arguments;

        $responseType = new Generic(StreamedResponse::class, [
            new StringType,
            new LiteralIntegerType(200),
            $headers,
        ]);

        $responseType->setAttribute(
            'mimeType',
            $this->guessMimeTypeFromFileType($file)
                ?: $this->guessMimeTypeFromFileType($name)
                ?: 'application/octet-stream',
        );

        return $responseType;
    }
}
