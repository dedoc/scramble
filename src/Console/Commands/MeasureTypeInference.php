<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Scope\Index;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use JsonException;
use Throwable;

class MeasureTypeInference extends Command
{
    protected $signature = 'scramble:measure-types
        {--source= : The OpenAPI generation measurement report to read}
        {--output= : The path to save the type inference measurement report}
        {--exceptions-output= : The path to save the thrown-exceptions measurement report}
    ';

    protected $description = 'Measure return-type inference for methods used during OpenAPI generation.';

    public function handle(Infer $infer): int
    {
        $source = $this->option('source') ?: base_path('openapi-generation-benchmark-Scramble.json');
        $output = $this->option('output') ?: base_path('route-return-type-benchmark-Scramble.json');
        $exceptionsOutput = $this->option('exceptions-output') ?: base_path('controller-thrown-exceptions-benchmark-Scramble.json');

        if (! is_string($source) || ! is_string($output) || ! is_string($exceptionsOutput)) {
            return self::FAILURE;
        }

        if (! File::exists($source)) {
            $this->error("OpenAPI generation measurement report is missing: {$source}");

            return self::FAILURE;
        }

        try {
            /** @var array{measurement_records?: array{'methods.non_vendor'?: list<array{0: class-string, 1: string}>, 'methods.vendor'?: list<array{0: class-string, 1: string}>}} $sourceReport */
            $sourceReport = json_decode(File::get($source), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error("Unable to read OpenAPI generation measurement report: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $targets = [];

        foreach ([
            ...($sourceReport['measurement_records']['methods.non_vendor'] ?? []),
            ...($sourceReport['measurement_records']['methods.vendor'] ?? []),
        ] as $target) {
            [$class, $method] = $target;
            $targets[$class.'::'.$method] = [$class, $method];
        }

        ksort($targets);

        if ($targets === []) {
            $this->error("OpenAPI generation measurement report does not contain any method targets: {$source}");

            return self::FAILURE;
        }

        $types = [];
        $resolved = 0;
        $unresolved = 0;
        $errors = 0;
        $startingRss = $this->peakResidentSetBytes();
        $startingMemory = memory_get_usage();
        memory_reset_peak_usage();
        $startedAt = hrtime(true);

        foreach ($targets as [$class, $method]) {
            $key = $class.'::'.$method;

            try {
                $type = $infer->index->getClass($class)?->getMethod($method)?->getReturnType()->toString();

                if ($type === null) {
                    $types[$key] = '(unresolved)';
                    $unresolved++;

                    continue;
                }

                $types[$key] = $type;
                $resolved++;
            } catch (Throwable $exception) {
                $types[$key] = '(error: '.$exception->getMessage().')';
                $errors++;
            }
        }

        $peakRss = $this->peakResidentSetBytes();
        $report = [
            'engine' => 'Scramble',
            'action_count' => count($targets),
            'elapsed_ms' => (hrtime(true) - $startedAt) / 1_000_000,
            'start_bytes' => $startingMemory,
            'memory_bytes' => memory_get_usage() - $startingMemory,
            'peak_bytes' => memory_get_peak_usage() - $startingMemory,
            'starting_rss_bytes' => $startingRss,
            'peak_rss_bytes' => $peakRss,
            'rss_peak_delta_bytes' => max(0, $peakRss - $startingRss),
            'resolved' => $resolved,
            'unresolved' => $unresolved,
            'errors' => $errors,
            'types' => $types,
        ];

        File::put($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

        Context::reset();
        app()->forgetInstance(Infer::class);
        app()->forgetInstance(Index::class);
        $exceptionsReport = $this->measureThrownExceptions(app(Infer::class));

        File::put($exceptionsOutput, json_encode($exceptionsReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

        $this->info(sprintf(
            'Scramble return-type benchmark: %d methods, %.3f ms, PHP %.2f MiB start / %.2f MiB delta / %.2f MiB peak; RSS %.2f MiB start / %.2f MiB peak / %.2f MiB delta; %d resolved, %d unresolved, %d errors',
            $report['action_count'],
            $report['elapsed_ms'],
            $report['start_bytes'] / 1_048_576,
            $report['memory_bytes'] / 1_048_576,
            $report['peak_bytes'] / 1_048_576,
            $report['starting_rss_bytes'] / 1_048_576,
            $report['peak_rss_bytes'] / 1_048_576,
            $report['rss_peak_delta_bytes'] / 1_048_576,
            $report['resolved'],
            $report['unresolved'],
            $report['errors'],
        ));
        $this->line("Report: {$output}");
        $this->info(sprintf(
            'Scramble thrown-exceptions benchmark: %d methods, %.3f ms, PHP %.2f MiB start / %.2f MiB delta / %.2f MiB peak; RSS %.2f MiB start / %.2f MiB peak / %.2f MiB delta; %d resolved, %d unresolved, %d errors; %d exceptions across %d methods',
            $exceptionsReport['action_count'],
            $exceptionsReport['elapsed_ms'],
            $exceptionsReport['start_bytes'] / 1_048_576,
            $exceptionsReport['memory_bytes'] / 1_048_576,
            $exceptionsReport['peak_bytes'] / 1_048_576,
            $exceptionsReport['starting_rss_bytes'] / 1_048_576,
            $exceptionsReport['peak_rss_bytes'] / 1_048_576,
            $exceptionsReport['rss_peak_delta_bytes'] / 1_048_576,
            $exceptionsReport['resolved'],
            $exceptionsReport['unresolved'],
            $exceptionsReport['errors'],
            $exceptionsReport['exception_count'],
            $exceptionsReport['methods_with_exceptions'],
        ));
        $this->line("Report: {$exceptionsOutput}");

        return self::SUCCESS;
    }

    /**
     * @return array{engine: string, action_count: int, elapsed_ms: float, start_bytes: int, memory_bytes: int, peak_bytes: int, starting_rss_bytes: int, peak_rss_bytes: int, rss_peak_delta_bytes: int, resolved: int, unresolved: int, errors: int, methods_with_exceptions: int, exception_count: int, exceptions: array<string, list<string>|string>}
     */
    private function measureThrownExceptions(Infer $infer): array
    {
        $targets = [];

        foreach (Route::getRoutes() as $route) {
            $class = $route->getControllerClass();
            $method = $route->getActionMethod();

            if (! is_string($class) || $class === '' || ! class_exists($class)) {
                continue;
            }

            if (! is_string($method) || $method === '' || $method === 'Closure') {
                continue;
            }

            $targets[$class.'::'.$method] = [$class, $method];
        }

        ksort($targets);

        $exceptions = [];
        $resolved = 0;
        $unresolved = 0;
        $errors = 0;
        $methodsWithExceptions = 0;
        $exceptionCount = 0;
        $startingRss = $this->peakResidentSetBytes();
        $startingMemory = memory_get_usage();
        memory_reset_peak_usage();
        $startedAt = hrtime(true);

        foreach ($targets as [$class, $method]) {
            $key = $class.'::'.$method;

            try {
                $definition = $infer->index->getClass($class)?->getMethod($method);

                if ($definition === null) {
                    $exceptions[$key] = '(unresolved)';
                    $unresolved++;

                    continue;
                }

                $inferredExceptions = array_map(
                    static fn ($exception): string => $exception->toString(),
                    $definition->type->exceptions,
                );

                $exceptions[$key] = $inferredExceptions;
                $resolved++;
                $exceptionCount += count($inferredExceptions);
                $methodsWithExceptions += $inferredExceptions === [] ? 0 : 1;
            } catch (Throwable $exception) {
                $exceptions[$key] = '(error: '.$exception->getMessage().')';
                $errors++;
            }
        }

        $peakRss = $this->peakResidentSetBytes();

        return [
            'engine' => 'Scramble',
            'action_count' => count($targets),
            'elapsed_ms' => (hrtime(true) - $startedAt) / 1_000_000,
            'start_bytes' => $startingMemory,
            'memory_bytes' => memory_get_usage() - $startingMemory,
            'peak_bytes' => memory_get_peak_usage() - $startingMemory,
            'starting_rss_bytes' => $startingRss,
            'peak_rss_bytes' => $peakRss,
            'rss_peak_delta_bytes' => max(0, $peakRss - $startingRss),
            'resolved' => $resolved,
            'unresolved' => $unresolved,
            'errors' => $errors,
            'methods_with_exceptions' => $methodsWithExceptions,
            'exception_count' => $exceptionCount,
            'exceptions' => $exceptions,
        ];
    }

    private function peakResidentSetBytes(): int
    {
        $maximumResidentSetSize = getrusage()['ru_maxrss'] ?? 0;

        return PHP_OS_FAMILY === 'Darwin'
            ? $maximumResidentSetSize
            : $maximumResidentSetSize * 1024;
    }
}
