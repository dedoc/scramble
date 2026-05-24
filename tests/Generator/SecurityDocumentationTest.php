<?php

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route as RouteFacade;

it('does not add security transformers multiple times for the same config', function () {
    RouteFacade::get(
        'api/protected',
        [GeneratorSecurityDocumentationTest_ProtectedController::class, 'index'],
    )->middleware('auth:sanctum');

    $config = Scramble::configure()
        ->useConfig(config('scramble'))
        ->routes(fn ($route) => $route->uri === 'api/protected');

    $generator = app()->make(Generator::class);

    $generator($config);

    $documentTransformersCount = count($config->documentTransformers->all());
    $operationTransformersCount = count($config->operationTransformers->all());

    $generator($config);
    $generator($config);

    expect(count($config->documentTransformers->all()))->toBe($documentTransformersCount)
        ->and(count($config->operationTransformers->all()))->toBe($operationTransformersCount);
});

class GeneratorSecurityDocumentationTest_ProtectedController
{
    public function index() {}
}
