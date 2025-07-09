<?php

namespace Dedoc\Scramble\Support\ExceptionToResponseExtensions;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;

class AuthenticationExceptionToResponseExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(AuthenticationException::class);
    }

    public function toResponse(Type $type)
    {
        $responseBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty(
                'message',
                (new OpenApiTypes\StringType)
                    ->setDescription('Error overview.')
            )
            ->setRequired(['message']);

        return Response::make(401)
            ->setDescription('Unauthenticated')
            ->setContent(
                'application/json',
                Schema::fromType($responseBodyType),
            );
    }

    public function reference(ObjectType $type)
    {
        return new Reference('responses', Str::start($type->name, '\\'), $this->components);
    }
}
