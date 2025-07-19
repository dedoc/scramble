<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
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
    /** @var string[] */
    public static array $nonBinaryMimeTypes = [
        // Plain text formats
        'text/plain',
        'text/html',
        'text/css',
        'text/csv',
        'text/xml',
        'text/javascript',

        // JSON and JavaScript formats
        'application/json',
        'application/javascript',
        'application/ecmascript',

        // XML and related formats
        'application/xml',
        'application/xhtml+xml',

        // Form data
        'application/x-www-form-urlencoded',

        // Web fonts and SVG
        'image/svg+xml',
        'application/font-woff',
        'application/font-woff2',

        // Other textual formats
        'application/graphql',
        'application/ld+json',
        'application/manifest+json',
    ];

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->isInstanceOf(BinaryFileResponse::class)
            && count($type->templateTypes) === 4;
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type): ?Response
    {
        if (! $status = $this->getStatus($type)) {
            return null;
        }

        return Response::make($status)
            ->setContent(
                $contentType = $this->getContentType($type),
                Schema::fromType($this->buildType($contentType)),
            )
            ->setHeaders($this->makeHeaders($type));
    }

    /**
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
        $attributeMimeType = is_string($attributeMimeType = $type->getAttribute('mimeType'))
            ? $attributeMimeType
            : null;

        return $this->guessMimeTypeFromHeaders($type->templateTypes[2 /* THeaders */])
            ?: $attributeMimeType
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

    private function buildType(string $contentType): OpenApiType
    {
        $type = new StringType;

        if (! in_array($contentType, static::$nonBinaryMimeTypes)) {
            $type->format('binary');
        }

        return $type;
    }
}
