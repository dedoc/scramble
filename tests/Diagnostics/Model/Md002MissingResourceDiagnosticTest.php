<?php

use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\Model\Md002MissingResourceDiagnostic;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\InferExtensions\ModelExtension;
use Dedoc\Scramble\Support\InferExtensions\TransformsToResourceCollectionExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

it('reports MD002 when a model resource cannot be guessed', function () {
    $context = md002DiagnosticFixture();

    $type = ReferenceTypeResolver::getInstance()->resolve(
        new GlobalScope,
        new MethodCallReferenceType(
            new ObjectType(Md002MissingResourceDiagnosticTest_Model::class),
            'toResource',
            [],
        ),
    );

    expect($type)->not->toBeNull();

    $diagnostic = $context->diagnostics->all()->sole();

    expect($diagnostic)->toBeInstanceOf(Md002MissingResourceDiagnostic::class)
        ->and($diagnostic->message())->toContain(Md002MissingResourceDiagnosticTest_Model::class)
        ->and($diagnostic->context())->toBeInstanceOf(ClassContext::class)
        ->and($diagnostic->context()->class)->toBe(Md002MissingResourceDiagnosticTest_Model::class);
})->skip(fn () => ! method_exists(Model::class, 'toResource'));

it('reports MD002 when a collection resource cannot be guessed', function () {
    $context = md002DiagnosticFixture();

    $type = ReferenceTypeResolver::getInstance()->resolve(
        new GlobalScope,
        new MethodCallReferenceType(
            new Generic(Collection::class, [
                new IntegerType,
                new ObjectType(Md002MissingResourceDiagnosticTest_Model::class),
            ]),
            'toResourceCollection',
            [],
        ),
    );

    expect($type)->not->toBeNull();

    $diagnostic = $context->diagnostics->all()->sole();

    expect($diagnostic)->toBeInstanceOf(Md002MissingResourceDiagnostic::class)
        ->and($diagnostic->message())->toContain(Md002MissingResourceDiagnosticTest_Model::class);
})->skip(fn () => ! method_exists(Collection::class, 'toResourceCollection'));

it('does not report MD002 when a resource is configured with UseResource', function () {
    $context = md002DiagnosticFixture();

    $type = ReferenceTypeResolver::getInstance()->resolve(
        new GlobalScope,
        new MethodCallReferenceType(
            new ObjectType(Md002MissingResourceDiagnosticTest_AttributedModel::class),
            'toResource',
            [],
        ),
    );

    expect($type)->not->toBeNull()
        ->and($context->diagnostics->all())->toBeEmpty();
})->skip(fn () => ! class_exists(UseResource::class) || ! method_exists(Model::class, 'toResource'));

function md002DiagnosticFixture(): OpenApiContext
{
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);

    Scramble::infer()->configure()->replaceExtensions([
        new ModelExtension($context->diagnostics),
        new TransformsToResourceCollectionExtension($context->diagnostics),
    ]);

    return $context;
}

class Md002MissingResourceDiagnosticTest_Model extends Model {}

#[UseResource(Md002MissingResourceDiagnosticTest_Resource::class)]
class Md002MissingResourceDiagnosticTest_AttributedModel extends Model {}

class Md002MissingResourceDiagnosticTest_Resource extends JsonResource {}
