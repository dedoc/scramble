<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BinaryFileResponseToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->isInstanceOf(BinaryFileResponse::class)
            && count($type->templateTypes) === 4;
    }

    /**
     * @param Generic $type
     */
    public function toResponse(Type $type): ?Response
    {
        if (! $status = $this->getStatus($type)) {
            return null;
        }

        return Response::make($status)
            ->setContent(
                $this->getContentType($type),
                Schema::fromType((new StringType())->format('binary')),
            )
            ->setHeaders($this->makeHeaders($type));
    }

    /**
     * @param Generic $type
     * @return array<string, Header>
     */
    private function makeHeaders(Generic $type): array
    {
        $headers = [];

        if ($contentDisposition = $type->getAttribute('contentDisposition')) {
            $headers['Content-Disposition'] = new Header(
                required: true,
                schema: Schema::fromType(new StringType),
                example: $contentDisposition,
            );
        }

        return $headers;
    }

    private function getStatus(Generic $type): ?int
    {
        $statusType = $type->templateTypes[1 /* TStatus */] ?? null;

        if (! $statusType instanceof LiteralIntegerType) {
            return null;
        }

        return $statusType->value;
    }

    private function getContentType(Generic $type): string
    {
        return $this->guessMimeTypeFromHeaders($type->templateTypes[2 /* THeaders */])
            ?: $type->getAttribute('mimeType')
            ?: 'application/octet-stream';
    }

    private function guessMimeTypeFromHeaders(Type $headersType): ?string
    {
        $stringLiteralContentTypeHeader = $headersType instanceof KeyedArrayType
            ? collect($headersType->items)
                ->first(function (ArrayItemType_ $t) {
                    return is_string($t->key)
                        && Str::lower($t->key) === 'content-type'
                        && $t->value instanceof LiteralStringType;
                })
                ?->value
            : null;

        if ($stringLiteralContentTypeHeader instanceof LiteralStringType) {
            return $stringLiteralContentTypeHeader->value;
        }

        return null;
    }
}
