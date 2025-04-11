<?php

use Dedoc\Scramble\Console\Commands\ExportDocumentation;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

it('should export the documentation', function () {
    $generator = app(Generator::class);
    $path = 'api.json';

    File::shouldReceive('put')
        ->once()
        ->with(
            $path,
            json_encode($generator(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

    artisan(ExportDocumentation::class)->assertOk();
});

it('should export the documentation to the path specified by the --path option', function () {
    $generator = app(Generator::class);
    $path = 'api-test.json';

    File::shouldReceive('put')
        ->once()
        ->with(
            $path,
            json_encode($generator(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

    artisan(ExportDocumentation::class, [
        '--path' => $path,
    ])->assertOk();
});

it('should export the documentation of the API specified by the --api option', function () {
    $api = 'v2';
    $exportPath = 'scramble/api-v2.json';
    $generator = app(Generator::class);

    Scramble::registerApi($api, [
        'api_path' => 'api/'.$api,
        'export_path' => $exportPath,
    ]);

    File::shouldReceive('put')
        ->once()
        ->with(
            $exportPath,
            json_encode($generator(Scramble::getGeneratorConfig($api)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

    artisan(ExportDocumentation::class, [
        '--api' => $api,
    ])->assertOk();
});

it('should export the documentation of the API specified by the --api option without export_path config', function () {
    $api = 'v2';
    $generator = app(Generator::class);

    Scramble::registerApi($api, [
        'api_path' => 'api/v2',
        'export_path' => null,
    ]);

    File::shouldReceive('put')
        ->once()
        ->with(
            'api-'.$api.'.json',
            json_encode($generator(Scramble::getGeneratorConfig($api)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

    artisan(ExportDocumentation::class, [
        '--api' => $api,
    ])->assertOk();
});
