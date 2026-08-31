<?php

use Dedoc\Scramble\Console\Commands\MeasureDocumentation;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

it('measures OpenAPI documentation generation', function () {
    File::shouldReceive('put')
        ->once()
        ->withArgs(function (string $path, string $contents): bool {
            $report = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            expect($path)->toBe(base_path('openapi-generation-benchmark-Scramble.json'))
                ->and($report['engine'])->toBe('Scramble Generator')
                ->and($report['path_count'])->toBeInt()
                ->and($report['diagnostic_count'])->toBeInt()
                ->and($report['measurements_ms'])->toBeArray()
                ->and($report['measurement_records'])->toBeArray();

            return true;
        });

    artisan(MeasureDocumentation::class)->assertOk();
});
