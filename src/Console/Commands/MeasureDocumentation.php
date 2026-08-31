<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Measure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MeasureDocumentation extends Command
{
    protected $signature = 'scramble:measure
        {--api=default : The API to measure documentation generation for}
    ';

    protected $description = 'Measure OpenAPI documentation generation performance.';

    public function handle(Generator $generator): int
    {
        $api = $this->option('api');
        $config = Scramble::getGeneratorConfig($api);

        Measure::reset();
        $startingMemory = memory_get_usage();
        memory_reset_peak_usage();
        $startedAt = hrtime(true);

        $result = $generator->generate($config);

        $report = [
            'engine' => 'Scramble Generator',
            'elapsed_ms' => (hrtime(true) - $startedAt) / 1_000_000,
            'memory_bytes' => memory_get_usage() - $startingMemory,
            'peak_bytes' => memory_get_peak_usage() - $startingMemory,
            'measurements_ms' => Measure::all(),
            'measurement_records' => Measure::records(),
            'path_count' => count($result->openApi()->paths),
            'diagnostic_count' => $result->diagnostics()->count(),
        ];

        $path = base_path('openapi-generation-benchmark-Scramble.json');

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

        $this->info(sprintf(
            'Scramble Generator OpenAPI benchmark: %.3f ms total, %.3f ms types, %.2f MiB delta, %.2f MiB peak, %d paths, %d diagnostics',
            $report['elapsed_ms'],
            $report['measurements_ms']['types'] ?? 0,
            $report['memory_bytes'] / 1_048_576,
            $report['peak_bytes'] / 1_048_576,
            $report['path_count'],
            $report['diagnostic_count'],
        ));
        $this->line("Report: {$path}");

        return self::SUCCESS;
    }
}
