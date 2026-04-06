<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

beforeEach(function () {
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $this->infer = app(Infer::class);
    $this->transformer = app()->make(TypeTransformer::class, [
        'context' => $this->context,
    ]);
});

test('to schema', function () {
    $type = getStatementType(JsonApiResourceTypeToSchemaTest_Resource::class.'::make()');

    $this->transformer->transform($type);

    expect($this->transformer->getComponents()->getSchema('JsonApiResourceTypeToSchemaTest_Resource')->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
            ],
            'type' => [
                'type' => 'string',
                'const' => 'json_api_resource_type_to_schema_test_',
            ],
            'attributes' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                    ],
                    'email' => [
                        'type' => 'string',
                    ],
                    'created_at' => [
                        'type' => ['string', 'null'],
                        'format' => 'date-time',
                    ],
                ],
            ],
        ],
        'required' => [
            'id',
            'type',
            'attributes',
        ],
    ]);
});
/**
 * @property-read SampleUserModel $resource
 */
class JsonApiResourceTypeToSchemaTest_Resource extends JsonApiResource
{
    public $attributes = [
        'name',
        'email',
        'created_at',
    ];
}
