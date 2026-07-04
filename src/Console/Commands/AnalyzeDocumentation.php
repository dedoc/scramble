<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Components\Block;
use Dedoc\Scramble\Console\Commands\Components\TermsOfContentItem;
use Dedoc\Scramble\Contracts\Diagnostics\CodedDiagnostic;
use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Contracts\Diagnostics\WithCodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Exceptions\ConsoleRenderable;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnalyzeDocumentation extends Command
{
    protected $signature = 'scramble:analyze
        {--api=default : The API to analyze}
    ';

    protected $description = 'Analyzes the documentation generation process to surface any issues.';

    public function handle(Generator $generator): int
    {
        $generator->setThrowExceptions(false);

        $apiOption = $this->option('api');
        $api = is_string($apiOption) ? $apiOption : 'default';

        $generator(Scramble::getGeneratorConfig($api));

        $context = $generator->context;
        assert($context instanceof OpenApiContext);

        $diagnostics = $context->diagnostics->diagnostics;

        $this->groupDiagnosticsByRoute($diagnostics)
            ->sortKeysUsing(static function (string $a, string $b): int {
                if ($a === '') {
                    return $b === '' ? 0 : 1;
                }
                if ($b === '') {
                    return -1;
                }

                return strcmp($a, $b);
            })
            ->each(function (Collection $routeDiagnostics, string $routeKey) {
                $this->renderDiagnosticsGroup($routeDiagnostics, $routeKey);
            });

        $errorCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)->count();
        $warningCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Warning)->count();

        if ($errorCount > 0) {
            $this->error($this->formatSummary($errorCount, $warningCount, isError: true));

            return static::FAILURE;
        }

        if ($warningCount > 0) {
            $this->warn($this->formatSummary($errorCount, $warningCount, isError: false));

            return static::SUCCESS;
        }

        $this->info('Everything is fine! Documentation is generated without any errors 🍻');

        return static::SUCCESS;
    }

    private function formatSummary(int $errors, int $warnings, bool $isError): string
    {
        $errorLabel = $errors.' '.Str::plural('error', $errors);
        $warningLabel = $warnings.' '.Str::plural('warning', $warnings);

        $bracket = $isError ? 'ERROR' : 'WARNING';

        return "[$bracket] Found $errorLabel, $warningLabel.";
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     * @return Collection<string, Collection<int, Diagnostic>>
     */
    private function groupDiagnosticsByRoute(Collection $diagnostics): Collection
    {
        return $diagnostics->groupBy(function (Diagnostic $d) {
            return '';
            $route = $d->route();

            return $route ? $this->getRouteKey($route) : '';
        });
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    private function renderDiagnosticsGroup(Collection $diagnostics, string $groupKey): void
    {
        if ($groupKey === '') {
            $diagnostics
                ->groupBy(fn (Diagnostic $d) => $d->context() ?: 'General')
                ->sortKeys()
                ->each(function (Collection $contextDiagnostics, string $context) {
                    $context = Str::replace(base_path().DIRECTORY_SEPARATOR, '', $context);

                    $this->line("<options=bold>{$context}</>");
                    $this->line('');

                    $contextDiagnostics->each(function (Diagnostic $d) {
                        $this->renderDiagnosticEntry($d);
                        $this->line('');
                    });
                });

            return;
        }

        $this->renderRouteDiagnosticsHeader($diagnostics);

        $byCategory = $diagnostics->groupBy(fn (Diagnostic $d) => $d->category() ?: 'General')->sortKeys();

        $byCategory->each(function (Collection $categoryDiagnostics, string $category) {
            $this->line("<options=bold>{$category}</>");
            $this->line('');

            $this->renderSeveritySection($categoryDiagnostics, DiagnosticSeverity::Error, 'Errors');
            $this->renderSeveritySection($categoryDiagnostics, DiagnosticSeverity::Warning, 'Warnings');

            $this->line('');
        });
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    private function renderSeveritySection(Collection $diagnostics, DiagnosticSeverity $severity, string $label): void
    {
        $section = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === $severity);
        if ($section->isEmpty()) {
            return;
        }

        $this->line("{$label} ({$section->count()})");
        $this->line('');

        $section->each(function (Diagnostic $d) {
            $this->renderDiagnosticEntry($d);
            $this->line('');
        });
    }

    /**
     * @param  Collection<int, Diagnostic>  $routeDiagnostics
     */
    private function renderRouteDiagnosticsHeader(Collection $routeDiagnostics): void
    {
        $first = $routeDiagnostics->first(fn (Diagnostic $d) => $d->route() !== null);
        if (! $first instanceof Diagnostic || ! $route = $first->route()) {
            return;
        }

        $method = implode('|', $route->methods());
        $errorCount = $routeDiagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)->count();
        $warningCount = $routeDiagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Warning)->count();

        $statsParts = [];
        if ($errorCount > 0) {
            $statsParts[] = '<fg=red>'.$errorCount.' '.Str::plural('error', $errorCount).'</>';
        }
        if ($warningCount > 0) {
            $statsParts[] = '<fg=yellow>'.$warningCount.' '.Str::plural('warning', $warningCount).'</>';
        }

        $stats = implode(', ', $statsParts);

        $right = '<options=bold;fg='.$this->getHttpMethodColor($method).'>'.$method."</> $route->uri $stats";

        $tocComponent = new TermsOfContentItem(
            right: $right,
            left: $this->getRouteAction($route),
        );

        $tocComponent->render($this->output);

        $this->line('');
    }

    private function renderDiagnosticEntry(Diagnostic $d): void
    {
        $pad = 4;

        if ($d instanceof CodedDiagnostic) {
            if ($d instanceof WithCodeLocation) {
                $this->output->writeln('    --> line '.$d->location->line.' ['.$d->code().']: '.$d->message());
            }

            if (method_exists($d, 'render')) {
                $d->render($this->output);
            } else {
                $message = Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', $d->message());
                $lines = explode("\n", $message);
                $first = Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', $lines[0]);
                $continuationLines = array_slice($lines, 1);

                (new Block(
                    "<options=bold>[{$d->code()}] {$first}</>",
                    $pad,
                ))->render($this->output);

                foreach ($continuationLines as $line) {
                    (new Block($line, $pad))->render($this->output);
                }
            }

            $this->output->writeln('');

            if ($d->tip() !== '') {
                (new Block("Tip: {$d->tip()}", $pad))->render($this->output);
            }

            (new Block("Docs: {$d->documentationUrl()}", $pad))->render($this->output);

            return;
        }

        $msg = Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', $d->message());
        (new Block($msg, $pad))->render($this->output);

        $exception = $d->toException();
        if ($exception instanceof ConsoleRenderable) {
            $exception->renderInConsole($this->output);
        }
    }

    private function getRouteKey(?Route $route): string
    {
        if (! $route) {
            return '';
        }

        $method = implode('|', $route->methods());
        $uses = $route->getAction('uses');
        $actionPart = is_string($uses) ? $uses : '';

        return $method.'.'.$actionPart;
    }

    private function getHttpMethodColor(string $method): string
    {
        return match ($method) {
            'POST', 'PUT' => 'blue',
            'DELETE' => 'red',
            default => 'yellow',
        };
    }

    public function getRouteAction(?Route $route): ?string
    {
        if (! $route) {
            return null;
        }

        $uses = $route->getAction('uses');
        if (! $uses || ! is_string($uses)) {
            return null;
        }

        if (count($parts = explode('@', $uses)) !== 2 || ! method_exists(...$parts)) {
            return null;
        }

        [$class, $method] = $parts;

        $eloquentClassName = Str::replace(['App\Http\Controllers\\', 'App\Http\\'], '', $class);

        return "<fg=gray>{$eloquentClassName}@{$method}</>";
    }
}
