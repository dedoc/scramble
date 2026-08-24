<?php

namespace Dedoc\Scramble\Support\InferExtensions\Concerns;

use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;

trait GuessesMimeTypeFromFileType
{
    protected function guessMimeTypeFromFileType(Type $fileType): ?string
    {
        $fileName = $this->guessFileNameFromType($fileType);

        if ($fileName && class_exists(ExtensionMimeTypeDetector::class)) {
            return (new ExtensionMimeTypeDetector)->detectMimeTypeFromPath($fileName);
        }

        return null;
    }

    protected function guessFileNameFromType(Type $fileType): ?string
    {
        $stringLiterals = (new TypeWalker)->findAll(
            Union::wrap(...array_filter([$fileType->getOriginal(), $fileType])),
            fn (Type $type) => $type instanceof LiteralStringType,
        );

        foreach (array_reverse($stringLiterals) as $stringLiteral) {
            if ($stringLiteral instanceof LiteralStringType && $this->isFileName($stringLiteral->value)) {
                return $stringLiteral->value;
            }
        }

        return null;
    }

    private function isFileName(string $value): bool
    {
        return (bool) preg_match('/^.*\.[^.]+$/', $value);
    }
}
