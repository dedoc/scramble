<?php

use Dedoc\Scramble\Attributes\Discriminator;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\Schema\Se002InvalidDiscriminatorMappingDiagnostic;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\DiscriminatedObjectToSchema;

it('reports SE002 when a mapped type cannot be documented', function () {
    [$context, $extension] = discriminatorDiagnosticFixture();

    $extension->toSchema(new ObjectType(Se002InvalidDiscriminatorMappingDiagnosticTest_Pet::class));

    $diagnostic = $context->diagnostics->all()->sole();

    expect($diagnostic)->toBeInstanceOf(Se002InvalidDiscriminatorMappingDiagnostic::class)
        ->and($diagnostic->message())->toBe('Cannot document [App\Models\Missing] from the discriminator mapping')
        ->and($diagnostic->context())->toBeInstanceOf(ClassContext::class)
        ->and($diagnostic->context()->class)->toBe(Se002InvalidDiscriminatorMappingDiagnosticTest_Pet::class);
});

it('does not report SE002 when all the mapped types can be documented', function () {
    [$context, $extension] = discriminatorDiagnosticFixture();

    $extension->toSchema(new ObjectType(Se002InvalidDiscriminatorMappingDiagnosticTest_ValidPet::class));

    expect($context->diagnostics->all())->toBeEmpty();
});

/**
 * @return array{0: OpenApiContext, 1: DiscriminatedObjectToSchema}
 */
function discriminatorDiagnosticFixture(): array
{
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $infer = app(Infer::class);
    $transformer = new TypeTransformer($infer, $context, [
        DiscriminatedObjectToSchema::class,
    ]);

    return [
        $context,
        new DiscriminatedObjectToSchema($infer, $transformer, $context->openApi->components, $context),
    ];
}

#[Discriminator('petType', ['cat' => Se002InvalidDiscriminatorMappingDiagnosticTest_Cat::class, 'dog' => 'App\Models\Missing'])]
abstract class Se002InvalidDiscriminatorMappingDiagnosticTest_Pet {}

#[Discriminator('petType', ['cat' => Se002InvalidDiscriminatorMappingDiagnosticTest_Cat::class])]
abstract class Se002InvalidDiscriminatorMappingDiagnosticTest_ValidPet {}

class Se002InvalidDiscriminatorMappingDiagnosticTest_Cat
{
    public string $petType = 'cat';
}
