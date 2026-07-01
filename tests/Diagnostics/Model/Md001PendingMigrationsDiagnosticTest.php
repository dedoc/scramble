<?php

use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\Model\Md001PendingMigrationsDiagnostic;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

it('reports MD001 via ModelInfo when the model table does not exist', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);

    (new ModelInfo(Md001PendingMigrationsDiagnosticTest_Model::class))->handle();

    expect($context->diagnostics->diagnostics)->toHaveCount(1);

    $diagnostic = $context->diagnostics->diagnostics->first();
    expect($diagnostic)->toBeInstanceOf(Md001PendingMigrationsDiagnostic::class)
        ->and($diagnostic->code())->toBe('MD001');
});

it('reports MD001 when the model table does not exist', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context);

    $type = new Generic(Md001PendingMigrationsDiagnosticTest_Resource::class, [new UnknownType]);
    $extension->toSchema($type);

    expect($context->diagnostics->diagnostics)->toHaveCount(1);

    $diagnostic = $context->diagnostics->diagnostics->first();
    expect($diagnostic)->toBeInstanceOf(Md001PendingMigrationsDiagnostic::class)
        ->and($diagnostic->code())->toBe('MD001')
        ->and($diagnostic->severity())->toBe(DiagnosticSeverity::Warning)
        ->and($diagnostic->category())->toBe('Models')
        ->and($diagnostic->context())->toBe(Md001PendingMigrationsDiagnosticTest_Model::class)
        ->and($diagnostic->message())->toContain(Md001PendingMigrationsDiagnosticTest_Model::class)
        ->and($diagnostic->message())->toContain('md001_pending_migrations_diagnostic_test_models');
});

it('does not report MD001 when the model table exists', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context);

    $type = new Generic(Md001PendingMigrationsDiagnosticTest_ResourceWithExistingTable::class, [new UnknownType]);
    $extension->toSchema($type);

    expect($context->diagnostics->diagnostics)->toBeEmpty();
});

it('reports MD001 only once per model class', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context);

    $type = new Generic(Md001PendingMigrationsDiagnosticTest_Resource::class, [new UnknownType]);
    $extension->toSchema($type);
    $extension->toSchema($type);

    expect($context->diagnostics->diagnostics)->toHaveCount(1);
});

/** @mixin Md001PendingMigrationsDiagnosticTest_Model */
class Md001PendingMigrationsDiagnosticTest_Resource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'name' => $this->name,
        ];
    }
}

class Md001PendingMigrationsDiagnosticTest_Model extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'md001_pending_migrations_diagnostic_test_models';
}

/** @mixin SampleUserModel */
class Md001PendingMigrationsDiagnosticTest_ResourceWithExistingTable extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'name' => $this->name,
        ];
    }
}
