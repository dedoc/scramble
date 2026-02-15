<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ArrayableToSchema;
use Illuminate\Contracts\Support\Arrayable;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context, [
        ArrayableToSchema::class,
    ]);
});

it('transforms arrayable to schema', function () {
    $schema = $this->transformer->transform(new ObjectType(Foo_ArrayableToSchemaTest::class));

    expect($schema->toArray())
        ->toBe(['$ref' => '#/components/schemas/Foo_ArrayableToSchemaTest'])
        ->and($this->components->getSchema('Foo_ArrayableToSchemaTest')->toArray())
        ->toBe([
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'const' => 42,
                ],
            ],
            'required' => ['id'],
        ]);
});
class Foo_ArrayableToSchemaTest implements Arrayable
{
    public function toArray()
    {
        return [
            'id' => 42,
        ];
    }
}
