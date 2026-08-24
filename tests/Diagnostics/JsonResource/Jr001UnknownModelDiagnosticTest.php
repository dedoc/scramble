<?php

use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\JsonResource\Jr001UnknownModelDiagnostic;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

it('reports JR001 when the underlying model type cannot be inferred', function () {
    [$context, $extension] = jsonResourceDiagnosticFixture();

    $extension->toSchema(new Generic(Jr001UnknownModelDiagnosticTest_Resource::class, [new UnknownType]));

    $diagnostic = $context->diagnostics->all()->sole();

    expect($diagnostic)->toBeInstanceOf(Jr001UnknownModelDiagnostic::class)
        ->and($diagnostic->context())->toBeInstanceOf(ClassContext::class)
        ->and($diagnostic->context()->class)->toBe(Jr001UnknownModelDiagnosticTest_Resource::class);
});

it('does not report JR001 when the model type is known via mixin', function () {
    [$context, $extension] = jsonResourceDiagnosticFixture();

    $extension->toSchema(new Generic(Jr001UnknownModelDiagnosticTest_ResourceWithMixin::class, [new UnknownType]));

    expect($context->diagnostics->all())->toBeEmpty();
});

/**
 * @return array{0: OpenApiContext, 1: JsonResourceTypeToSchema}
 */
function jsonResourceDiagnosticFixture(): array
{
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        JsonResourceTypeToSchema::class,
    ]);

    return [
        $context,
        new JsonResourceTypeToSchema($infer, $transformer, $context->openApi->components, $context),
    ];
}

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
    protected $table = 'users';
}
