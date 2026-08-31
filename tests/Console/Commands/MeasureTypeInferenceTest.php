<?php

use Dedoc\Scramble\Console\Commands\MeasureTypeInference;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

it('requires an OpenAPI generation measurement report', function () {
    $source = base_path('missing-openapi-generation-benchmark-Scramble.json');

    File::shouldReceive('exists')
        ->once()
        ->with($source)
        ->andReturnFalse();

    artisan(MeasureTypeInference::class, ['--source' => $source])
        ->expectsOutput("OpenAPI generation measurement report is missing: {$source}")
        ->assertFailed();
});

it('measures return types and thrown exceptions', function () {
    $source = base_path('openapi-generation-benchmark-Scramble.json');

    File::shouldReceive('exists')
        ->once()
        ->with($source)
        ->andReturnTrue();
    File::shouldReceive('get')
        ->once()
        ->with($source)
        ->andReturn(json_encode([
            'measurement_records' => [
                'methods.non_vendor' => [['Missing\\Class', 'missingMethod']],
            ],
        ], JSON_THROW_ON_ERROR));
    File::shouldReceive('put')
        ->twice()
        ->withArgs(function (string $path, string $contents): bool {
            $report = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            expect($path)->toBeIn([
                base_path('route-return-type-benchmark-Scramble.json'),
                base_path('controller-thrown-exceptions-benchmark-Scramble.json'),
            ])->and($report['engine'])->toBe('Scramble');

            return true;
        });

    artisan(MeasureTypeInference::class)->assertOk();
});
