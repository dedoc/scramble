<?php

use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\JsonResource\Jr001UnknownModelDiagnostic;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResourceCollectionTypeToSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

beforeEach(function () {
    JsonResourceHelper::$jsonResourcesModelTypesCache = [];
});

it('reports JR001 when the underlying model type cannot be inferred', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context);

    $type = new Generic(Jr001UnknownModelDiagnosticTest_Resource::class, [new UnknownType]);
    $extension->toSchema($type);

    expect($context->diagnostics->diagnostics)->toHaveCount(1);

    $diagnostic = $context->diagnostics->diagnostics->first();
    expect($diagnostic)->toBeInstanceOf(Jr001UnknownModelDiagnostic::class)
        ->and($diagnostic->code())->toBe('JR001')
        ->and($diagnostic->severity())->toBe(DiagnosticSeverity::Warning)
        ->and($diagnostic->category())->toBe('JSON resources')
        ->and($diagnostic->context())->toBe(Jr001UnknownModelDiagnosticTest_Resource::class)
        ->and($diagnostic->message())->toContain(Jr001UnknownModelDiagnosticTest_Resource::class);
});

it('does not report JR001 when the model type is known via mixin', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context);

    $type = new Generic(Jr001UnknownModelDiagnosticTest_ResourceWithMixin::class, [new UnknownType]);
    $extension->toSchema($type);

    expect($context->diagnostics->diagnostics)->toBeEmpty();
});

it('does not report JR001 for resource collections via ResourceCollectionTypeToSchema', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
        ResourceCollectionTypeToSchema::class,
    ]);
    $extension = new ResourceCollectionTypeToSchema($infer, $transformer, $context->openApi->components, $context);

    $type = new Generic(AnonymousResourceCollection::class, [
        new UnknownType,
        new UnknownType,
        new ObjectType(Jr001UnknownModelDiagnosticTest_ResourceWithMixin::class),
    ]);

    $extension->toSchema($type);

    expect($context->diagnostics->diagnostics)->toBeEmpty();
});

it('reports JR001 only once per resource class', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context);

    $type = new Generic(Jr001UnknownModelDiagnosticTest_Resource::class, [new UnknownType]);
    $extension->toSchema($type);
    $extension->toSchema($type);

    expect($context->diagnostics->diagnostics)->toHaveCount(1);
});

class Jr001UnknownModelDiagnosticTest_Resource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'name' => $this->name,
        ];
    }
}

/** @mixin Jr001UnknownModelDiagnosticTest_Model */
class Jr001UnknownModelDiagnosticTest_ResourceWithMixin extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'name' => $this->name,
        ];
    }
}

class Jr001UnknownModelDiagnosticTest_Model extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'jr001_unknown_model_diagnostic_test_models';
}
