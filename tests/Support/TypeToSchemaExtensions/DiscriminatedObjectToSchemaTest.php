<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Attributes\Discriminator;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\DiscriminatedObjectToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EnumToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\PlainObjectToSchema;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context, [
        PlainObjectToSchema::class,
        EnumToSchema::class,
        DiscriminatedObjectToSchema::class,
    ]);
});

it('transforms a discriminated class to oneOf of the mapped schemas', function () {
    $schema = $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_Pet::class));

    expect($schema->toArray())
        ->toBe(['$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Pet'])
        ->and($this->components->getSchema('DiscriminatedObjectToSchemaTest_Pet')->toArray())
        ->toBe([
            'oneOf' => [
                ['$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Cat'],
                ['$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Dog'],
            ],
            'discriminator' => [
                'propertyName' => 'petType',
                'mapping' => [
                    'cat' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Cat',
                    'dog' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Dog',
                ],
            ],
        ]);
});

it('produces the same schema when transformed more than once', function () {
    $first = $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_Pet::class));
    $schemaAfterFirstTransform = $this->components->getSchema('DiscriminatedObjectToSchemaTest_Pet')->toArray();

    $second = $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_Pet::class));

    expect($second->toArray())->toBe($first->toArray())
        ->and($this->components->getSchema('DiscriminatedObjectToSchemaTest_Pet')->toArray())
        ->toBe($schemaAfterFirstTransform);
});

it('documents the mapped types as schemas', function () {
    $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_Pet::class));

    expect($this->components->getSchema('DiscriminatedObjectToSchemaTest_Cat')->toArray())
        ->toBe([
            'type' => 'object',
            'properties' => [
                'petType' => ['type' => 'string', 'const' => 'cat'],
                'huntingSkill' => ['type' => 'string'],
            ],
            'required' => ['petType', 'huntingSkill'],
        ]);
});

it('documents the const on a property documented as a reference', function () {
    $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_EnumPet::class));
    $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_EnumOwner::class));

    expect($this->components->getSchema('DiscriminatedObjectToSchemaTest_EnumCat')->toArray()['properties']['petType'])
        ->toBe([
            'const' => 'cat',
            '$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_PetType',
        ])
        // The enum itself stays untouched.
        ->and($this->components->getSchema('DiscriminatedObjectToSchemaTest_PetType')->toArray())
        ->toBe(['type' => 'string', 'enum' => ['cat', 'dog']])
        ->and($this->components->getSchema('DiscriminatedObjectToSchemaTest_EnumOwner')->toArray()['properties']['petType'])
        ->toBe(['$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_PetType']);
});

it('does not document a const for a type mapped to several values', function () {
    $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_RepeatedPet::class));

    expect($this->components->getSchema('DiscriminatedObjectToSchemaTest_Cat')->toArray()['properties']['petType'])
        ->toBe(['type' => 'string']);
});

it('leaves a mapped type without the discriminator property alone', function () {
    $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_UnpinnablePet::class));

    expect($this->components->getSchema('DiscriminatedObjectToSchemaTest_Nameless')->toArray())
        ->toBe([
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ]);
});

it('omits the mapping when the mapped types are not keyed', function () {
    $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_ImplicitPet::class));

    expect($this->components->getSchema('DiscriminatedObjectToSchemaTest_ImplicitPet')->toArray())
        ->toBe([
            'oneOf' => [
                ['$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Cat'],
                ['$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Dog'],
            ],
            'discriminator' => [
                'propertyName' => 'petType',
            ],
        ])
        ->and($this->components->getSchema('DiscriminatedObjectToSchemaTest_Cat')->toArray()['properties']['petType'])
        ->toBe(['type' => 'string']);
});

it('supports interfaces', function () {
    $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_PetContract::class));

    expect($this->components->getSchema('DiscriminatedObjectToSchemaTest_PetContract')->toArray())
        ->toBe([
            'oneOf' => [
                ['$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Cat'],
            ],
            'discriminator' => [
                'propertyName' => 'petType',
                'mapping' => [
                    'cat' => '#/components/schemas/DiscriminatedObjectToSchemaTest_Cat',
                ],
            ],
        ]);
});

it('does not handle the classes without the mapped types', function () {
    $extension = new DiscriminatedObjectToSchema(app(Infer::class), $this->transformer, $this->components, $this->context);

    expect($extension->shouldHandle(new ObjectType(DiscriminatedObjectToSchemaTest_Cat::class)))->toBeFalse()
        ->and($extension->shouldHandle(new ObjectType(DiscriminatedObjectToSchemaTest_EmptyPet::class)))->toBeFalse()
        ->and($extension->reference(new ObjectType(DiscriminatedObjectToSchemaTest_EmptyPet::class)))->toBeNull();
});

it('documents a class without the mapped types the usual way', function () {
    $schema = $this->transformer->transform(new ObjectType(DiscriminatedObjectToSchemaTest_EmptyPet::class));

    expect($schema->toArray())
        ->toBe(['$ref' => '#/components/schemas/DiscriminatedObjectToSchemaTest_EmptyPet'])
        ->and($this->components->getSchema('DiscriminatedObjectToSchemaTest_EmptyPet')->toArray())
        ->toBe([
            'type' => 'object',
            'properties' => ['petType' => ['type' => 'string']],
            'required' => ['petType'],
        ]);
});

#[Discriminator('petType', ['cat' => DiscriminatedObjectToSchemaTest_Cat::class, 'dog' => DiscriminatedObjectToSchemaTest_Dog::class])]
abstract class DiscriminatedObjectToSchemaTest_Pet
{
    public string $petType;
}

#[Discriminator('petType', [DiscriminatedObjectToSchemaTest_Cat::class, DiscriminatedObjectToSchemaTest_Dog::class])]
abstract class DiscriminatedObjectToSchemaTest_ImplicitPet
{
    public string $petType;
}

#[Discriminator('petType')]
class DiscriminatedObjectToSchemaTest_EmptyPet
{
    public string $petType;
}

#[Discriminator('petType', ['cat' => DiscriminatedObjectToSchemaTest_Cat::class])]
interface DiscriminatedObjectToSchemaTest_PetContract {}

class DiscriminatedObjectToSchemaTest_Cat extends DiscriminatedObjectToSchemaTest_Pet implements DiscriminatedObjectToSchemaTest_PetContract
{
    public string $huntingSkill = 'lazy';
}

class DiscriminatedObjectToSchemaTest_Dog extends DiscriminatedObjectToSchemaTest_Pet
{
    public int $packSize = 1;
}

enum DiscriminatedObjectToSchemaTest_PetType: string
{
    case Cat = 'cat';
    case Dog = 'dog';
}

#[Discriminator('petType', ['cat' => DiscriminatedObjectToSchemaTest_EnumCat::class])]
abstract class DiscriminatedObjectToSchemaTest_EnumPet
{
    public DiscriminatedObjectToSchemaTest_PetType $petType;
}

class DiscriminatedObjectToSchemaTest_EnumCat extends DiscriminatedObjectToSchemaTest_EnumPet {}

class DiscriminatedObjectToSchemaTest_EnumOwner
{
    public DiscriminatedObjectToSchemaTest_PetType $petType;
}

#[Discriminator('petType', ['cat' => DiscriminatedObjectToSchemaTest_Cat::class, 'kitten' => DiscriminatedObjectToSchemaTest_Cat::class])]
abstract class DiscriminatedObjectToSchemaTest_RepeatedPet {}

#[Discriminator('petType', ['nameless' => DiscriminatedObjectToSchemaTest_Nameless::class])]
abstract class DiscriminatedObjectToSchemaTest_UnpinnablePet {}

class DiscriminatedObjectToSchemaTest_Nameless
{
    public string $name = 'x';
}
