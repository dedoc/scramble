<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RedirectResponseToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(RedirectResponse::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type): Response
    {
        $statusType = $type instanceof Generic
            ? ($type->templateTypes[1 /* TStatus */] ?? null)
            : null;

        return Response::make($statusType instanceof LiteralIntegerType ? $statusType->value : 302)
            ->addHeader('Location', new Header(required: true, schema: Schema::fromType(new StringType)));
    }
}
