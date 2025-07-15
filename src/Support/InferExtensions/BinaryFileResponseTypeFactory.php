<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Str;
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

    /** @return $this */
    public function setName(Type $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @return $this */
    public function setHeaders(Type $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /** @return $this */
    public function setDisposition(Type $disposition): self
    {
        $this->disposition = $disposition;

        return $this;
    }

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

        $fileName = $this->guessFileNameFromType($this->file);
        if ($fileName && class_exists(ExtensionMimeTypeDetector::class)) {
            $mimeType = (new ExtensionMimeTypeDetector)->detectMimeTypeFromPath($fileName);
        }

        /** @var LiteralStringType|null $stringLiteralContentTypeHeader */
        $stringLiteralContentTypeHeader = $this->headers instanceof KeyedArrayType
            ? collect($this->headers->items)
                ->first(function (ArrayItemType_ $t) {
                    return is_string($t->key)
                        && Str::lower($t->key) === 'content-type'
                        && $t->value instanceof LiteralStringType;
                })
                ?->value
            : null;
        if ($stringLiteralContentTypeHeader) {
            $mimeType = $stringLiteralContentTypeHeader->value;
        }

        return $mimeType;
    }

    private function guessContentDisposition(): ?string
    {
        $fileName = $this->guessFileNameFromType($this->file);
        $overridingFileName = $this->guessFileNameFromType($this->name);

        $contentDisposition = $this->disposition instanceof LiteralStringType ? $this->disposition->value : null;
        if ($contentDisposition === 'attachment') {
            $contentDisposition = $this->getContentDispositionAttachmentHeader(
                $fileName,
                $overridingFileName,
            );
        }

        return $contentDisposition;
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
