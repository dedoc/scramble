<?php

use Dedoc\Scramble\Diagnostics\Model\Md001PendingMigrationsDiagnostic;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\InferExtensions\ModelExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

it('reports MD001 when the model table does not exist', function () {
    [$context, $extension] = md001DiagnosticFixture();

    $extension->toSchema(new Generic(Md001PendingMigrationsDiagnosticTest_Resource::class, [new UnknownType]));

    $diagnostic = $context->diagnostics->all()->sole();

    expect($diagnostic)->toBeInstanceOf(Md001PendingMigrationsDiagnostic::class)
        ->and($diagnostic->message())->toContain(Md001PendingMigrationsDiagnosticTest_Model::class)
        ->and($diagnostic->message())->toContain('md001_pending_migrations_diagnostic_test_models');
});

it('does not report MD001 when the model table exists', function () {
    [$context, $extension] = md001DiagnosticFixture();

    $extension->toSchema(new Generic(Md001PendingMigrationsDiagnosticTest_ResourceWithExistingTable::class, [new UnknownType]));

    expect($context->diagnostics->all())->toBeEmpty();
});

/**
 * @return array{0: OpenApiContext, 1: JsonResourceTypeToSchema}
 */
function md001DiagnosticFixture(): array
{
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);

    Scramble::infer()->configure()->replaceExtensions([
        new ModelExtension($context->diagnostics),
    ]);

    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
    ]);

    return [
        $context,
        new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context),
    ];
}

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
