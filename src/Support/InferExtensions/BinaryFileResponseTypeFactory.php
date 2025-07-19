<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @see BinaryFileResponse
 *
 * @internal
 */
class BinaryFileResponseTypeFactory
{
    public function __construct(
        public Type $file,
        public Type $name = new NullType,
        public Type $headers = new ArrayType,
        public Type $disposition = new NullType,
    ) {}

    public function build(): Generic
    {
        $responseType = new Generic(BinaryFileResponse::class, [
            $this->file,
            new LiteralIntegerType(200),
            $this->headers,
            $this->disposition,
        ]);

        $responseType->setAttribute('mimeType', $this->guessMimeType());
        $responseType->setAttribute('contentDisposition', $this->guessContentDisposition());

        return $responseType;
    }

    private function guessMimeType(): string
    {
        $mimeType = 'application/octet-stream';

        if ($fileMime = $this->guessMimeTypeFromFile()) {
            $mimeType = $fileMime;
        }

        return $mimeType;
    }

    private function guessMimeTypeFromFile(): ?string
    {
        $fileName = $this->guessFileNameFromType($this->file);

        if ($fileName && class_exists(ExtensionMimeTypeDetector::class)) {
            return (new ExtensionMimeTypeDetector)->detectMimeTypeFromPath($fileName);
        }

        return null;
    }

    private function guessContentDisposition(): ?string
    {
        $contentDisposition = $this->disposition instanceof LiteralStringType ? $this->disposition->value : null;

        if ($contentDisposition !== 'attachment') {
            return $contentDisposition;
        }

        return $this->getContentDispositionAttachmentHeader(
            $this->guessFileNameFromType($this->file),
            $this->guessFileNameFromType($this->name),
        );
    }

    private function guessFileNameFromType(Type $fileArgumentType): ?string
    {
        $stringLiterals = (new TypeWalker)->findAll(
            Union::wrap(...array_filter([$fileArgumentType->getOriginal(), $fileArgumentType])),
            fn (Type $t) => $t instanceof LiteralStringType,
        );

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
        return (bool) preg_match('/^.*\.[^.]+$/', $str);
    }

    private function getContentDispositionAttachmentHeader(?string $fileName, ?string $overridingFileName): string
    {
        if (! $fileName && ! $overridingFileName) {
            return 'attachment';
        }

        $name = $overridingFileName ?: $fileName;

        return 'attachment; filename='.basename($name);
    }
}
