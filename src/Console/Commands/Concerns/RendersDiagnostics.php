<?php

namespace Dedoc\Scramble\Console\Commands\Concerns;

use Closure;
use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Console\Commands\Components\StyledConsoleTextWrapper;
use Dedoc\Scramble\Console\Commands\Components\TermsOfContentItem;
use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Support\ProNudge\ProNudgeReporter;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Terminal;

trait RendersDiagnostics
{
    private function getDiagnosticsBasedReturnCode(Generator $generator): int
    {
        $errorCount = $generator->diagnostics
            ->all()
            ->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)
            ->count();

        return $errorCount ? static::FAILURE : static::SUCCESS;
    }

    private function renderDiagnostics(Generator $generator, string $successMessage, Closure $issuesMessage): void
    {
        $diagnostics = $generator->diagnostics->all();

        $i = 1;
        $this->groupDiagnostics($diagnostics)->each(function (Collection $groupDiagnostics, string $groupKey) use (&$i) {
            $this->renderDiagnosticsGroup($groupDiagnostics, $groupKey, $i);
        });

        $this->renderDiagnosticsSummary(
            $generator,
            $successMessage,
            $issuesMessage,
        );
    }

    private function renderDiagnosticsSummary(Generator $generator, string $successMessage, Closure $issuesMessage): void
    {
        $diagnostics = $generator->diagnostics->all();

        $errorCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)->count();
        $warningCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Warning)->count();

        $summary = collect([
            $errorCount > 0 ? $errorCount.' '.Str::plural('error', $errorCount) : null,
            $warningCount > 0 ? $warningCount.' '.Str::plural('warning', $warningCount) : null,
        ])->filter()->implode(' and ');

        if ($errorCount > 0) {
            $this->warn('[ERROR] '.$issuesMessage($summary));
            $this->renderProNudge($generator);

            return;
        }

        if ($warningCount > 0) {
            $this->warn('[WARNING] '.$issuesMessage($summary));
        } else {
            $this->info($successMessage);
        }

        $this->renderProNudge($generator);
    }

    private function renderProNudge(Generator $generator): void
    {
        (new ProNudgeReporter($generator->proNudge))->report($this);
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     * @return Collection<string, Collection<int, Diagnostic>>
     */
    private function groupDiagnostics(Collection $diagnostics): Collection
    {
        return $diagnostics->groupBy(function (Diagnostic $d) {
            $context = $d->context();

            if ($context instanceof ClassContext) {
                return 'class:'.$context->class;
            }

            if ($context instanceof Route) {
                return 'route:'.$this->getRouteKey($context);
            }

            return '';
        })->sortBy(fn (Collection $_, string $key) => match (true) {
            str_starts_with($key, 'route:') => 0,
            str_starts_with($key, 'class:') => 1,
            default => 2,
        });
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    private function renderDiagnosticsGroup(Collection $diagnostics, string $groupKey, int &$i): void
    {
        if (str_starts_with($groupKey, 'route:')) {
            $this->renderRouteGroupHeader($diagnostics);
        } elseif (str_starts_with($groupKey, 'class:')) {
            $this->renderClassGroupHeader($diagnostics);
        }

        $diagnostics->each(function (Diagnostic $diagnostic) use (&$i) {
            $this->renderDiagnostic($diagnostic, $i);
            $i++;
            $this->line('');
        });
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    private function renderRouteGroupHeader(Collection $diagnostics): void
    {
        $route = $diagnostics->first()?->context();
        if (! $route instanceof Route) {
            return;
        }

        $method = implode('|', $route->methods());
        $stats = $this->severityStats($diagnostics);

        $tocComponent = new TermsOfContentItem(
            right: '<options=bold;fg='.$this->getHttpMethodColor($method).'>'.$method."</> $route->uri".($stats ? " $stats" : ''),
            left: $this->getRouteAction($route),
        );

        $tocComponent->render($this->output);
        $this->line('');
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    private function renderClassGroupHeader(Collection $diagnostics): void
    {
        $class = $diagnostics->first()?->context();
        if (! $class instanceof ClassContext) {
            return;
        }

        $stats = $this->severityStats($diagnostics);
        $right = class_basename($class->class).($stats ? " $stats" : '');

        (new TermsOfContentItem(right: $right, left: ' '))->render($this->output);
        $this->line('');
    }

    private function renderDiagnostic(Diagnostic $diagnostic, int $i): void
    {
        $message = Str::replace(
            'Dedoc\Scramble\Support\Generator\Types\\',
            '',
            $diagnostic->message(),
        );

        $level = match ($diagnostic->severity()) {
            DiagnosticSeverity::Error => '<fg=red;options=bold>ERR</>',
            DiagnosticSeverity::Warning => '<fg=yellow;options=bold>WARN</>',
        };

        $this->line("$i. $level <options=bold>[{$diagnostic->code()}] {$message}</>");

        $details = $diagnostic->details();

        $location = $diagnostic->codeLocation();
        $shouldRenderCodeSnippet = $diagnostic->shouldRenderCodeSnippet() && $location;

        if ($shouldRenderCodeSnippet) {
            $this->renderTable($details);

            (new Code($location->file, $location->line))->render($this->output);
        }

        $postfixTableRows = $shouldRenderCodeSnippet ? [] : $details;
        if ($tip = $diagnostic->tip()) {
            $postfixTableRows[] = ['Tip', $tip];
        }

        $this->renderTable($postfixTableRows);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     */
    private function renderTable(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $maxLabelLength = max(array_map(fn (array $row) => strlen($row[0]), $rows));
        $terminalWidth = (new Terminal)->getWidth();

        $this->output->createTable()
            ->setRows(array_map(
                fn (array $row) => [
                    '<fg=gray>'.$row[0].'</>',
                    implode(
                        "\n",
                        array_map('trim', (new StyledConsoleTextWrapper)->wrap($row[1], $terminalWidth - $maxLabelLength - 2)),
                    ),
                ],
                $rows,
            ))
            ->setStyle('compact')
            ->render();
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    private function severityStats(Collection $diagnostics): string
    {
        $errorCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)->count();
        $warningCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Warning)->count();

        return collect([
            $errorCount > 0 ? "<fg=red>$errorCount ".Str::plural('error', $errorCount).'</>' : null,
            $warningCount > 0 ? "<fg=yellow>$warningCount ".Str::plural('warning', $warningCount).'</>' : null,
        ])->filter()->implode(', ');
    }

    private function getRouteKey(Route $route): string
    {
        $method = implode('|', $route->methods());
        $action = $route->getAction('uses');

        return "$method.$action";
    }

    private function getHttpMethodColor(string $method): string
    {
        return match ($method) {
            'POST', 'PUT' => 'blue',
            'DELETE' => 'red',
            default => 'yellow',
        };
    }

    private function getRouteAction(?Route $route): ?string
    {
        if (! $route || ! $uses = $route->getAction('uses')) {
            return null;
        }

        if (! is_string($uses)) {
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
