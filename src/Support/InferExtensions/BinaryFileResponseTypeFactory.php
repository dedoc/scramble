<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Support\InferExtensions\Concerns\GuessesMimeTypeFromFileType;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\Type;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @see BinaryFileResponse
 *
 * @internal
 */
class BinaryFileResponseTypeFactory
{
    use GuessesMimeTypeFromFileType;

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

        if ($fileMime = $this->guessMimeTypeFromFileType($this->file)) {
            $mimeType = $fileMime;
        }

        return $mimeType;
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

    private function getContentDispositionAttachmentHeader(?string $fileName, ?string $overridingFileName): string
    {
        if (! $fileName && ! $overridingFileName) {
            return 'attachment';
        }

        $name = $overridingFileName ?: $fileName;

        return 'attachment; filename='.basename($name);
    }
}
