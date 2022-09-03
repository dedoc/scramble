<?php

use Dedoc\Scramble\Support\BuiltInExtensions\JsonResourceOpenApi;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Infer\Infer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Illuminate\Http\Resources\Json\JsonResource;
use function Spatie\Snapshots\assertMatchesSnapshot;

it('transforms simple types', function ($type, $openApiArrayed) {
    $transformer = new TypeTransformer(new Infer, new Components);

    expect($transformer->transform($type)->toArray())->toBe($openApiArrayed);
})->with([
    [new IntegerType(), ['type' => 'integer']],
    [new StringType(), ['type' => 'string']],
    [new BooleanType(), ['type' => 'boolean']],
    [new ArrayType([
        new ArrayItemType_(0, new StringType()),
    ]), ['type' => 'array', 'items' => ['type' => 'string']]],
    [new ArrayType([
        new ArrayItemType_('key', new IntegerType()),
        new ArrayItemType_('optional_key', new IntegerType(), true),
    ]), [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'integer'],
            'optional_key' => ['type' => 'integer'],
        ],
        'required' => ['key'],
    ]],
]);

it('gets json resource type', function () {
    $transformer = new TypeTransformer($infer = new Infer, $components = new Components, [JsonResourceOpenApi::class]);
    $extension = new JsonResourceOpenApi($infer, $transformer, $components);

    $type = new ObjectType(ComplexTypeHandlersTest_SampleType::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});

it('gets json resource type with nested merges', function () {
    $transformer = new TypeTransformer($infer = new Infer, $components = new Components, [JsonResourceOpenApi::class]);
    $extension = new JsonResourceOpenApi($infer, $transformer, $components);

    $type = new ObjectType(ComplexTypeHandlersWithNestedTest_SampleType::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});

it('gets json resource type reference', function () {
    $transformer = new TypeTransformer(new Infer, $components = new Components, [JsonResourceOpenApi::class]);

    $type = new ObjectType(ComplexTypeHandlersTest_SampleType::class);

    expect($transformer->transform($type)->toArray())->toBe([
        '$ref' => '#/components/schemas/ComplexTypeHandlersTest_SampleType',
    ]);

    assertMatchesSnapshot($components->getSchema(ComplexTypeHandlersTest_SampleType::class)->toArray());
});

class ComplexTypeHandlersTest_SampleType extends JsonResource
{
    public function toArray($request)
    {
        return [
            'foo' => 1,
            $this->mergeWhen(true, [
                'hey' => 'ho',
            ]),
            $this->merge([
                'bar' => 'foo',
            ]),
        ];
    }
}

class ComplexTypeHandlersWithNestedTest_SampleType extends JsonResource
{
    public function toArray($request)
    {
        return [
            'foo' => 1,
            'wait' => [
                'one' => 1,
                $this->merge([
                    'bar' => 'foo',
                    'kek' => [
                        $this->merge([
                            'bar' => 'foo',
                        ]),
                    ],
                ]),
            ],
            $this->mergeWhen(true, [
                'hey' => 'ho',
            ]),
            $this->merge([
                'bar' => 'foo',
            ]),
        ];
    }
}
