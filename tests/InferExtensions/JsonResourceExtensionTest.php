<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ModelToSchema;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->infer = app(Infer::class);
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
});

/**
 * @return array{0: \Dedoc\Scramble\Support\Generator\Types\Type, 1: Components}
 */
function JsonResourceExtensionTest_analyze(Infer $infer, OpenApiContext $context, string $class)
{
    $transformer = new TypeTransformer($infer, $context, [
        ModelToSchema::class,
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context);

    $type = new ObjectType($class);

    $openApiType = $extension->toSchema($type);

    return [$openApiType, $context->openApi->components];
}

it('supports whenHas', function () {
    [$schema] = JsonResourceExtensionTest_analyze($this->infer, $this->context, JsonResourceExtensionTest_WhenHas::class);

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'user' => [
                '$ref' => '#/components/schemas/SampleUserModel',
            ],
            'value' => [
                'type' => 'integer',
                'enum' => [42],
            ],
            'default' => [
                'anyOf' => [
                    [
                        'type' => 'string',
                        'enum' => ['foo'],
                    ],
                    [
                        'type' => 'integer',
                        'enum' => [42],
                    ],
                ],
            ],
        ],
        'required' => ['default'],
    ]);
});
/** @mixin SamplePostModel */
class JsonResourceExtensionTest_WhenHas extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'user' => $this->whenHas('user'),
            'value' => $this->whenHas('user', 42),
            'default' => $this->whenHas('user', 42, 'foo'),
        ];
    }
}

class JsonResourceExtensionTest_MatchWithThrow extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'property' => match (rand(0, 2)) {
                0 => 'foo',
                1 => throw new Exception('foo'),
                default => 123,
            },
        ];
    }
}

it('supports match with throw', function () {
    [$schema] = JsonResourceExtensionTest_analyze($this->infer, $this->context, JsonResourceExtensionTest_MatchWithThrow::class);

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'property' => [
                'anyOf' => [
                    ['type' => 'string', 'enum' => ['foo']],
                    ['type' => 'integer', 'enum' => [123]],
                ],
            ],
        ],
        'required' => ['property'],
    ]);
});
