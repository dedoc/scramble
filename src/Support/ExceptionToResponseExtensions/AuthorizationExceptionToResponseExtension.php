<?php

namespace Dedoc\Scramble\Support\ExceptionToResponseExtensions;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class AuthorizationExceptionToResponseExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(AuthorizationException::class);
    }

    public function toResponse(Type $type)
    {
        $validationResponseBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty(
                'message',
                (new OpenApiTypes\StringType)
                    ->setDescription('Error overview.')
            )
            ->setRequired(['message']);

        return Response::make(403)
            ->setDescription('Authorization error')
            ->setContent(
                'application/json',
                Schema::fromType($validationResponseBodyType),
            );
    }

    public function reference(ObjectType $type)
    {
        return new Reference('responses', Str::start($type->name, '\\'), $this->components);
    }
}
