<?php

use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResponseTypeToSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $this->infer = app(Infer::class);
    $this->transformer = app()->make(TypeTransformer::class, [
        'context' => $this->context,
    ]);
});

it('supports call to method', function () {
    $type = new Generic(JsonResourceTypeToSchemaTest_WithInteger::class, [new UnknownType]);

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    expect($extension->toSchema($type)->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'res_int' => ['type' => 'integer'],
            'proxy_int' => ['type' => 'integer'],
        ],
        'required' => ['res_int', 'proxy_int'],
    ]);
});

it('supports parent toArray class', function (string $className, array $expectedSchemaArray) {
    $type = new Generic($className, [new UnknownType]);

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    expect($extension->toSchema($type)->toArray())->toBe($expectedSchemaArray);
})->with([
    [JsonResourceTypeToSchemaTest_NestedSample::class, [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'foo' => ['type' => 'string', 'enum' => ['bar']],
            'nested' => ['type' => 'string', 'enum' => ['true']],
        ],
        'required' => ['id', 'name', 'foo', 'nested'],
    ]],
    [JsonResourceTypeToSchemaTest_Sample::class, [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
        ],
        'required' => ['id', 'name'],
    ]],
    [JsonResourceTypeToSchemaTest_SpreadSample::class, [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'foo' => ['type' => 'string', 'enum' => ['bar']],
        ],
        'required' => ['id', 'name', 'foo'],
    ]],
    [JsonResourceTypeToSchemaTest_NoToArraySample::class, [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
        ],
        'required' => ['id', 'name'],
    ]],
]);
/**
 * @property-read JsonResourceTypeToSchemaTest_User $resource
 */
class JsonResourceTypeToSchemaTest_NoToArraySample extends JsonResource {}
/**
 * @property JsonResourceTypeToSchemaTest_User $resource
 */
class JsonResourceTypeToSchemaTest_SpreadSample extends JsonResource
{
    public function toArray($request): array
    {
        return [
            ...parent::toArray($request),
            'foo' => 'bar',
        ];
    }
}
/**
 * @property JsonResourceTypeToSchemaTest_User $resource
 */
class JsonResourceTypeToSchemaTest_NestedSample extends JsonResourceTypeToSchemaTest_SpreadSample
{
    public function toArray($request): array
    {
        return [
            ...parent::toArray($request),
            'nested' => 'true',
        ];
    }
}
/**
 * @property JsonResourceTypeToSchemaTest_User $resource
 */
class JsonResourceTypeToSchemaTest_Sample extends JsonResource
{
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}

it('handles withResponse for json api resource', function () {
    $type = new Generic(JsonResourceTypeToSchemaTest_WithResponseSample::class, [new UnknownType]);

    $extension = new JsonResourceTypeToSchema($this->infer, $this->transformer, $this->context->openApi->components, $this->context);

    expect($extension->toResponse($type)->code)->toBe(429);
});

/**
 * @property JsonResourceTypeToSchemaTest_User $resource
 */
class JsonResourceTypeToSchemaTest_WithResponseSample extends JsonResource
{
    public function withResponse(Request $request, \Illuminate\Http\JsonResponse $response)
    {
        $response->setStatusCode(429);
    }
}

it('properly handles custom status code', function () {
    $openApiDocument = generateForRoute(function () {
        return Route::get('api/test', JsonResourceTypeToSchemaTest_StatusCodeController::class);
    });

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses)
        ->toHaveKey('201')
        ->not->toHaveKey('429')
        ->and($responses['201']['content']['application/json']['schema'])
        ->toHaveKey('properties')
        ->and($responses['201']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null)
        ->toBe('#/components/schemas/JsonResourceTypeToSchemaTest_WithResponseSample');
});

class JsonResourceTypeToSchemaTest_StatusCodeController
{
    public function __invoke()
    {
        return (new JsonResourceTypeToSchemaTest_WithResponseSample)
            ->response()
            ->setStatusCode(201);
    }
}

/**
 * @property JsonResourceTypeToSchemaTest_User $resource
 */
class JsonResourceTypeToSchemaTest_WithInteger extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'res_int' => $this->resource->getInteger(),
            'proxy_int' => $this->getInteger(),
        ];
    }
}

it('gets the underlying model when mixin is inline', function () {
    $infer = app(Infer::class);

    $model = JsonResourceHelper::modelType(
        $infer->analyzeClass(JsonResourceTypeToSchemaTest_WithIntegerInline::class),
        new Infer\Scope\GlobalScope,
    );

    expect($model->name)->toBe(JsonResourceTypeToSchemaTest_User::class);
});

/** @mixin JsonResourceTypeToSchemaTest_User */
class JsonResourceTypeToSchemaTest_WithIntegerInline extends JsonResource {}

class JsonResourceTypeToSchemaTest_User extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'users';

    protected $visible = ['id', 'name'];

    public function getInteger(): int
    {
        return unknown();
    }
}

it('handles default in json api resource', function () {
    $type = new Generic(JsonResourceTypeToSchemaTest_WithDefault::class, [new UnknownType]);

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [
        JsonResourceTypeToSchema::class,
        ResponseTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    expect($extension->toSchema($type)->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'foo' => [
                'type' => 'string',
                'default' => 'fooBar',
            ],
        ],
        'required' => ['foo'],
    ]);
});
class JsonResourceTypeToSchemaTest_WithDefault extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            /**
             * @default fooBar
             */
            'foo' => $this->resource->foo,
        ];
    }
}

it('handles additional data with custom status code', function () {
    $openApiDocument = generateForRoute(function () {
        return Route::get('api/test', [JsonResourceTypeToSchemaTest_AdditionalController::class, 'index']);
    });

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses)
        ->toHaveKey('202')
        ->not->toHaveKey('200')
        ->and($responses['202']['content']['application/json']['schema']['properties'])
        ->toHaveKeys(['data', 'meta'])
        ->and($responses['202']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null)
        ->toBe('#/components/schemas/JsonResourceTypeToSchemaTest_Sample')
        ->and($responses['202']['content']['application/json']['schema']['properties']['meta'])
        ->toBe([
            'type' => 'object',
            'properties' => ['foo' => ['type' => 'string', 'enum' => ['bar']]],
            'required' => ['foo'],
        ]);
});
class JsonResourceTypeToSchemaTest_AdditionalController
{
    public function index()
    {
        return (new JsonResourceTypeToSchemaTest_Sample)
            ->additional(['meta' => ['foo' => 'bar']])
            ->response()
            ->setStatusCode(202);
    }
}

it('handles supports custom schema name', function () {
    $openApiDocument = generateForRoute(function () {
        return Route::get('api/test', [JsonResourceTypeToSchemaTest_CustomSchemaTest::class, 'index']);
    });

    $responses = dd($openApiDocument)['paths']['/test']['get']['responses'];

    expect($responses)
        ->toHaveKey('202')
        ->not->toHaveKey('200')
        ->and($responses['202']['content']['application/json']['schema']['properties'])
        ->toHaveKeys(['data', 'meta'])
        ->and($responses['202']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null)
        ->toBe('#/components/schemas/JsonResourceTypeToSchemaTest_Sample')
        ->and($responses['202']['content']['application/json']['schema']['properties']['meta'])
        ->toBe([
            'type' => 'object',
            'properties' => ['foo' => ['type' => 'string', 'example' => 'bar']],
            'required' => ['foo'],
        ]);
})->skip();
class JsonResourceTypeToSchemaTest_CustomSchemaTest
{
    public function index()
    {
        return new JsonResourceTypeToSchemaTest_WithCustomName;
    }
}
#[SchemaName('CustomName')]
class JsonResourceTypeToSchemaTest_WithCustomName extends JsonResource
{
    public function toArray(Request $request)
    {
        return ['foo' => 'bar'];
    }
}
