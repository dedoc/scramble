<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;

class ResponseTypeToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && (
                $type->isInstanceOf(\Illuminate\Http\Response::class)
                || $type->isInstanceOf(JsonResponse::class)
            );
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type)
    {
        if (! $type->templateTypes[1] instanceof LiteralIntegerType) {
            return null;
        }

        $emptyContent = ($type->templateTypes[0]->value ?? null) === '';

        return Response::make($code = $type->templateTypes[1]->value)
            ->description($code === 204 ? 'No content' : '')
            ->setContent(
                'application/json', // @todo: Some other response types are possible as well
                $emptyContent
                    ? null
                    : Schema::fromType($this->openApiTransformer->transform($type->templateTypes[0])),
            );
    }
}
