<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Concerns\CreatesGenerator;
use Dedoc\Scramble\Console\Commands\Concerns\RendersDiagnostics;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\GeneratorResult;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionFunction;
use Symfony\Component\Console\Formatter\OutputFormatter;

class AnalyzeDocumentation extends Command
{
    use CreatesGenerator;
    use RendersDiagnostics;

    protected $signature = 'scramble:analyze
        {--api=default : The API to analyze}
        {--routes= : Comma-separated route names to analyze}
        {--fail-on-unknown : Fail when an UnknownType schema is generated}
        {--fail-on-empty : Fail when the generated documentation contains no paths}
    ';

    protected $description = 'Analyzes the documentation generation process to surface any issues.';

    public function handle(): int
    {
        $routeProvider = $this->createRouteProvider();
        $generator = $this->createGenerator($routeProvider);
        $generator->setThrowExceptions(false);
        Scramble::throwOnError(false);

        if ($this->option('fail-on-unknown')) {
            Scramble::preventSchema(UnknownType::class, throw: false);
        }

        $api = $this->option('api');
        if (! is_string($api)) {
            return self::FAILURE;
        }

        $config = Scramble::getGeneratorConfig($api);
        $result = $generator->generate($config);
        $routeCount = $routeProvider->get($config)->count();

        $this->renderRouteSelection($config, $result, $routeCount);

        if ($result->diagnostics()->isNotEmpty()) {
            $this->newLine();
        }

        $this->renderDiagnostics(
            result: $result,
            successMessage: null,
            issuesMessage: fn ($summary) => "Found {$summary}."
        );

        if ($this->option('fail-on-empty') && $result->openApi()->paths === []) {
            return self::FAILURE;
        }

        return $this->getDiagnosticsBasedReturnCode($result);
    }

    private function renderRouteSelection(GeneratorConfig $config, GeneratorResult $result, int $routeCount): void
    {
        $operationCount = collect($result->openApi()->paths)->sum(fn ($path) => count($path->operations));

        if ($routeCount === 0) {
            $this->warn("[WARNING] No routes matched API [{$config->name}].");
        } else {
            $this->info(
                'Matched '.$routeCount.' '.Str::plural('route', $routeCount)
                ." for API [{$config->name}], producing ".$operationCount.' OpenAPI '
                .Str::plural('operation', $operationCount).'.'
            );
        }

        $this->newLine();

        if ($routeResolver = $config->routeResolver()) {
            $reflection = new ReflectionFunction($routeResolver);
            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();
            $source = is_string($file) && is_int($line)
                ? ' at '.OutputFormatter::escape("{$file}:{$line}")
                : '';

            $this->line("Selection rule: custom matcher registered with `routes()`{$source}.");
            $this->line('The `api_path` and `api_domain` settings are ignored for route selection when a custom matcher is used.');
        } else {
            $apiPath = $config->apiPath();

            $this->line('Selection rule:');
            $this->line('  Include paths: '.($apiPath->includes === [] ? 'any' : implode(', ', $apiPath->includes)));

            if ($apiPath->excludes !== []) {
                $this->line('  Exclude paths: '.implode(', ', $apiPath->excludes));
            }

            $this->line('  Domain: '.($config->get('api_domain') ?: 'any'));
        }

        if (is_string($routes = $this->option('routes')) && trim($routes) !== '') {
            $this->line('  Route names: '.$routes);
        }
    }
}
