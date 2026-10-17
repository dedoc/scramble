<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
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

    public function toResponse(Type $type): Response
    {
        return Response::make(302)
            ->addHeader('Location', new Header(required: true, schema: Schema::fromType(new StringType)));
    }
}
