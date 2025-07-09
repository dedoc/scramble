<?php

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\AuthenticationExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
});

it('correctly overrides default extension when custom extension exists', function () {
    $type = new ObjectType(AuthenticationException::class);

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [], [
        AuthenticationExceptionToResponseExtension::class,
        CustomAuthenticationExceptionToResponseExtension::class,
    ]);
    $extension = new CustomAuthenticationExceptionToResponseExtension($infer, $transformer, $this->components);

    expect($extension->toResponse($type)->toArray())->toMatchArray($transformer->toResponse($type)->resolve()->toArray());
});

class CustomAuthenticationExceptionToResponseExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
               && $type->isInstanceOf(AuthenticationException::class);
    }

    public function toResponse(Type $type)
    {
        return Response::make(401)
            ->setDescription('Custom Unauthenticated')
            ->setContent(
                'application/json',
                Schema::fromType((new OpenApiTypes\ObjectType)),
            );
    }

    public function reference(ObjectType $type)
    {
        return new Reference('responses', Str::start($type->name, '\\'), $this->components);
    }
}
