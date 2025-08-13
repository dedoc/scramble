<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Str;
use LogicException;

class ResponseTypeToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && (
                $type->isInstanceOf(\Illuminate\Http\Response::class)
                || $type->isInstanceOf(JsonResponse::class)
            )
            && count($type->templateTypes) >= 2;
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type)
    {
        if ($this->isResponsable($type) && $responsableResponse = $this->handleResponsableResponse($type)) {
            return $responsableResponse;
        }

        if (! $type->templateTypes[1] instanceof LiteralIntegerType) {
            return null;
        }

        $emptyContent = $type->templateTypes[0] instanceof NullType
            || ($type->templateTypes[0]->value ?? null) === '';

        $response = Response::make($code = $type->templateTypes[1]->value)
            ->description($code === 204 ? 'No content' : '');

        if (! $emptyContent) {
            $response->setContent(
                'application/json',
                Schema::fromType($this->openApiTransformer->transform($type->templateTypes[0])),
            );
        }

        $this->addHeaders($response, $type);

        return $response;
    }

    private function isResponsable(Generic $type): bool
    {
        $data = $type->templateTypes[0];

        return $data instanceof ObjectType && $data->isInstanceOf(Responsable::class);
    }

    private function handleResponsableResponse(Generic $jsonResponseType): ?Response
    {
        $data = $jsonResponseType->templateTypes[0];
        $statusCode = $jsonResponseType->templateTypes[1];

        $response = $this->openApiTransformer->toResponse($data);
        if (! $response instanceof Response) {
            throw new LogicException("{$data->toString()} is expected to produce Response instance when casted to response.");
        }

        $responseStatusCode = $statusCode instanceof UnknownType
            ? $response->code
            : ($statusCode->value ?? null);

        if (! $responseStatusCode) {
            return null;
        }

        $response->code = $responseStatusCode;
        if (! $data->isInstanceOf(ResourceResponse::class)) {
            $response->setContent(
                'application/json',
                Schema::fromType($this->openApiTransformer->transform($data)),
            );
        }

        $this->addHeaders($response, $jsonResponseType);

        return $response;
    }

    private function addHeaders(Response $response, Generic $type): void
    {
        $headersType = $type->templateTypes[2] ?? null;

        if (! $headersType instanceof KeyedArrayType) {
            return;
        }

        foreach ($headersType->items as $item) {
            $this->addHeader($response, $item);
        }
    }

    private function addHeader(Response $response, ArrayItemType_ $item): void
    {
        if (! $key = $this->getNormalizedHeaderKey($item)) {
            return;
        }

        if (Str::lower($key) === 'content-type') {
            $this->handleContentTypeHeader($response, $item);

            return;
        }

        $response->addHeader(
            $key,
            new Header(schema: Schema::fromType($this->openApiTransformer->transform($item->value))),
        );
    }

    private function handleContentTypeHeader(Response $response, ArrayItemType_ $item): void
    {
        $contentTypeValue = $item->value instanceof LiteralStringType ? $item->value->value : null;

        if (! $contentTypeValue) {
            return;
        }

        if (! $firstMediaType = array_keys($response->content)[0] ?? null) {
            return;
        }

        $mediaType = $response->getContent($firstMediaType);

        unset($response->content[$firstMediaType]);
        $response->content[$contentTypeValue] = $mediaType;
    }

    private function getNormalizedHeaderKey(ArrayItemType_ $item): ?string
    {
        $key = $item->key ?: ($item->keyType instanceof LiteralStringType ? $item->keyType->value : null);

        return is_string($key) ? $key : null;
    }
}
