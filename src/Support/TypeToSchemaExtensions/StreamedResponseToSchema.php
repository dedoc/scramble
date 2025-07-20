<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @see StreamedResponse
 * @see StreamedJsonResponse
 */
class StreamedResponseToSchema extends TypeToSchemaExtension
{
    public static string $defaultMimeType = 'text/html';

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->isInstanceOf(StreamedResponse::class)
            && count($type->templateTypes) === 3;
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
            ->setDescription($this->makeDescription($type))
            ->setContent(
                $this->getContentType($type),
                Schema::fromType($this->makeType($type)),
            )
            ->setHeaders($this->makeHeaders());
    }

    private function getStatus(Generic $type): ?int
    {
        $statusType = $type->templateTypes[1 /* TStatus */] ?? null;

        if (! $statusType instanceof LiteralIntegerType) {
            return null;
        }

        return $statusType->value;
    }

    private function isServerSentEventsResponse(Generic $type): bool
    {
        return $type->getAttribute('mimeType') === 'text/event-stream';
    }

    private function makeDescription(Generic $type): string
    {
        if (! $this->isServerSentEventsResponse($type)) {
            return '';
        }

        $description = 'A server-sent events (SSE) streamed response.';

        if ($endStreamWith = $this->getEndStreamWith($type)) {
            $description .= " `$endStreamWith` update will be sent to the event stream when the stream is complete.";
        }

        return $description;
    }

    private function getContentType(Generic $type): string
    {
        $attributeMimeType = is_string($attributeMimeType = $type->getAttribute('mimeType'))
            ? $attributeMimeType
            : null;

        return $attributeMimeType
            ?: $this->guessMimeTypeFromHeaders($type->templateTypes[2 /* THeaders */])
            ?: ($type->isInstanceOf(StreamedJsonResponse::class) ? 'application/json' : self::$defaultMimeType)
            ?: self::$defaultMimeType;
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

    private function makeType(Generic $type): OpenApiType
    {
        if ($type->isInstanceOf(StreamedJsonResponse::class)) {
            return $this->openApiTransformer->transform($type->templateTypes[0 /* TData */]);
        }

        if ($this->isServerSentEventsResponse($type)) {
            $schema = (new ObjectType)
                ->addProperty('event', (new StringType)->example('update'))
                ->addProperty('data', new MixedType)
                ->setRequired(['event', 'data']);

            if ($example = $this->makeExample($type)) {
                $schema->examples([$example]);
            }

            return $schema;
        }

        return new StringType;
    }

    private function makeExample(Generic $type): ?string
    {
        if (! $this->isServerSentEventsResponse($type)) {
            return null;
        }

        $example = "event: update\ndata: {data}\n\n";

        if ($endStreamWith = $this->getEndStreamWith($type)) {
            $example .= "event: update\ndata: $endStreamWith\n\n";
        }

        return $example;
    }

    /**
     * @return array<string, Header>
     */
    private function makeHeaders(): array
    {
        return [
            'Transfer-Encoding' => new Header(
                required: true,
                schema: Schema::fromType((new StringType)->enum(['chunked'])),
            ),
        ];
    }

    private function getEndStreamWith(Generic $type): ?string
    {
        $endStreamWith = $type->getAttribute('endStreamWith');

        return is_string($endStreamWith) ? $endStreamWith : null;
    }
}
