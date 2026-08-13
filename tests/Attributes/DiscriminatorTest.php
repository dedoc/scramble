<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\Discriminator;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

it('documents a polymorphic response as oneOf with a discriminator', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', PetController_DiscriminatorTest::class));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/Pet_DiscriminatorTest'])
        ->and($openApiDocument['components']['schemas']['Pet_DiscriminatorTest'])
        ->toBe([
            'oneOf' => [
                ['$ref' => '#/components/schemas/Cat_DiscriminatorTest'],
                ['$ref' => '#/components/schemas/Dog_DiscriminatorTest'],
            ],
            'discriminator' => [
                'propertyName' => 'petType',
                'mapping' => [
                    'cat' => '#/components/schemas/Cat_DiscriminatorTest',
                    'dog' => '#/components/schemas/Dog_DiscriminatorTest',
                ],
            ],
            'title' => 'Pet_DiscriminatorTest',
        ])
        ->and($openApiDocument['components']['schemas'])
        ->toHaveKeys(['Cat_DiscriminatorTest', 'Dog_DiscriminatorTest']);
});

#[Discriminator('petType', ['cat' => Cat_DiscriminatorTest::class, 'dog' => Dog_DiscriminatorTest::class])]
abstract class Pet_DiscriminatorTest
{
    public string $petType;
}

class Cat_DiscriminatorTest extends Pet_DiscriminatorTest
{
    public string $huntingSkill = 'lazy';
}

class Dog_DiscriminatorTest extends Pet_DiscriminatorTest
{
    public int $packSize = 1;
}

class PetController_DiscriminatorTest
{
    public function __invoke(): Pet_DiscriminatorTest
    {
        return resolve_pet();
    }
}

it('takes precedence over the extensions handling the annotated type', function () {
    Scramble::registerExtension(PetLikeTypeToSchema_DiscriminatorTest::class);

    $openApiDocument = generateForRoute(fn () => Route::get('test', PetController_DiscriminatorTest::class));

    expect($openApiDocument['components']['schemas']['Pet_DiscriminatorTest'])
        ->toBe([
            'oneOf' => [
                ['$ref' => '#/components/schemas/Cat_DiscriminatorTest'],
                ['$ref' => '#/components/schemas/Dog_DiscriminatorTest'],
            ],
            'discriminator' => [
                'propertyName' => 'petType',
                'mapping' => [
                    'cat' => '#/components/schemas/Cat_DiscriminatorTest',
                    'dog' => '#/components/schemas/Dog_DiscriminatorTest',
                ],
            ],
            'title' => 'Pet_DiscriminatorTest',
        ])
        ->and($openApiDocument['components']['schemas']['Cat_DiscriminatorTest'])
        ->toBe([
            'type' => 'object',
            'properties' => ['handledByExtension' => ['type' => 'boolean']],
            'title' => 'Cat_DiscriminatorTest',
        ]);
});

/** Stands in for an extension documenting a whole class hierarchy, Laravel Data objects for example. */
class PetLikeTypeToSchema_DiscriminatorTest extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && is_a($type->name, Pet_DiscriminatorTest::class, true);
    }

    public function toSchema(Type $type): OpenApiType
    {
        return (new OpenApiObjectType)->addProperty('handledByExtension', new BooleanType);
    }

    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}

it('documents a polymorphic JSON resource response as oneOf with a discriminator', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', PetResourceController_DiscriminatorTest::class));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => [
                'data' => ['$ref' => '#/components/schemas/PetResource_DiscriminatorTest'],
            ],
            'required' => ['data'],
        ])
        ->and($openApiDocument['components']['schemas']['PetResource_DiscriminatorTest'])
        ->toBe([
            'oneOf' => [
                ['$ref' => '#/components/schemas/CatResource_DiscriminatorTest'],
                ['$ref' => '#/components/schemas/DogResource_DiscriminatorTest'],
            ],
            'discriminator' => [
                'propertyName' => 'petType',
                'mapping' => [
                    'cat' => '#/components/schemas/CatResource_DiscriminatorTest',
                    'dog' => '#/components/schemas/DogResource_DiscriminatorTest',
                ],
            ],
            'title' => 'PetResource_DiscriminatorTest',
        ]);
});

#[Discriminator('petType', ['cat' => CatResource_DiscriminatorTest::class, 'dog' => DogResource_DiscriminatorTest::class])]
abstract class PetResource_DiscriminatorTest extends JsonResource {}

class CatResource_DiscriminatorTest extends PetResource_DiscriminatorTest
{
    public function toArray($request)
    {
        return [
            'petType' => 'cat',
            'huntingSkill' => 'lazy',
        ];
    }
}

class DogResource_DiscriminatorTest extends PetResource_DiscriminatorTest
{
    public function toArray($request)
    {
        return [
            'petType' => 'dog',
            'packSize' => 1,
        ];
    }
}

class PetResourceController_DiscriminatorTest
{
    public function __invoke(): PetResource_DiscriminatorTest
    {
        return resolve_pet_resource();
    }
}
