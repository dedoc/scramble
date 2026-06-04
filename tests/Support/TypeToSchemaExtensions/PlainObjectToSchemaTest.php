<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\PlainObjectToSchema;
use JsonSerializable;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context, [
        PlainObjectToSchema::class,
    ]);
});

it('handles plain object types', function () {
    $extension = new PlainObjectToSchema(
        app(Infer::class),
        $this->transformer,
        $this->components,
    );

    expect($extension->shouldHandle(new ObjectType(PlainObjectToSchemaTest_User::class)))->toBeTrue();
});

it('transforms plain object public properties to schema', function () {
    $schema = $this->transformer->transform(new ObjectType(PlainObjectToSchemaTest_User::class));

    expect($schema->toArray())
        ->toBe(['$ref' => '#/components/schemas/PlainObjectToSchemaTest_User'])
        ->and($this->components->getSchema('PlainObjectToSchemaTest_User')->toArray())
        ->toBe([
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                ],
                'name' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['id', 'name'],
        ]);
});

class PlainObjectToSchemaTest_User
{
    public int $id = 42;

    public string $name = 'Jane';

    protected string $secret = 'hidden';
}

it('transforms json serializable plain object to schema', function () {
    $schema = $this->transformer->transform(new ObjectType(PlainObjectToSchemaTest_Profile::class));

    expect($schema->toArray())
        ->toBe(['$ref' => '#/components/schemas/PlainObjectToSchemaTest_Profile'])
        ->and($this->components->getSchema('PlainObjectToSchemaTest_Profile')->toArray())
        ->toBe([
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'const' => 'a@b.c',
                ],
            ],
            'required' => ['email'],
        ]);
});

class PlainObjectToSchemaTest_Profile implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return [
            'email' => 'a@b.c',
        ];
    }
}
