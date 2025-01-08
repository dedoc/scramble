<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

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

        $emptyContent = ($type->templateTypes[0]->value ?? null) === '';

        $response = Response::make($code = $type->templateTypes[1]->value)
            ->description($code === 204 ? 'No content' : '');

        if (! $emptyContent) {
            $response->setContent(
                'application/json', // @todo: Some other response types are possible as well
                Schema::fromType($this->openApiTransformer->transform($type->templateTypes[0])),
            );
        }

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

        $responseStatusCode = $statusCode instanceof UnknownType
            ? $response->code
            : ($statusCode->value ?? null);

        if (! $responseStatusCode) {
            return null;
        }

        $response->code = $responseStatusCode;

        return $response;
    }
}
