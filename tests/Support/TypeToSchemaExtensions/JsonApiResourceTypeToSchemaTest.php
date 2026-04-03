<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Reflection\ReflectionJsonApiResource;
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

    $schema = $this->transformer->transform($type);
    dd($schema->toArray());
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
