<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Console\Commands\Components\TermsOfContentItem;
use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\SchemaContext;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\ProNudge\ProNudgeReporter;
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

        $generator(Scramble::getGeneratorConfig($this->option('api')));

        $context = $generator->context;
        assert($context instanceof OpenApiContext);

        $diagnostics = $context->diagnostics->diagnostics;

        $i = 1;
        $this->groupDiagnostics($diagnostics)->each(function (Collection $groupDiagnostics, string $groupKey) use (&$i) {
            $this->renderDiagnosticsGroup($groupDiagnostics, $groupKey, $i);
        });

        $errorCount = $diagnostics
            ->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)
            ->count();

        if ($errorCount > 0) {
            $this->error('[ERROR] Found '.$errorCount.' '.Str::plural('error', $errorCount).'.');

            (new ProNudgeReporter($generator->proNudge))->report($this);

            return static::FAILURE;
        }

        $this->info('Everything is fine! Documentation is generated without any errors 🍻');

        (new ProNudgeReporter($generator->proNudge))->report($this);

        return static::SUCCESS;
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     * @return Collection<string, Collection<int, Diagnostic>>
     */
    private function groupDiagnostics(Collection $diagnostics): Collection
    {
        return $diagnostics->groupBy(function (Diagnostic $d) {
            $context = $d->context();

            if ($context instanceof SchemaContext) {
                return 'schema:'.$context->name;
            }

            if ($context instanceof Route) {
                return 'route:'.$this->getRouteKey($context);
            }

            return '';
        });
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    private function renderDiagnosticsGroup(Collection $diagnostics, string $groupKey, int &$i): void
    {
        if (str_starts_with($groupKey, 'route:')) {
            $this->renderRouteGroupHeader($diagnostics);
        } elseif (str_starts_with($groupKey, 'schema:')) {
            $this->renderSchemaGroupHeader($diagnostics);
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
        $errorCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)->count();
        $warningCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Warning)->count();

        $stats = collect([
            $errorCount > 0 ? $errorCount.' '.Str::plural('error', $errorCount) : null,
            $warningCount > 0 ? $warningCount.' '.Str::plural('warning', $warningCount) : null,
        ])->filter()->implode(', ');

        $tocComponent = new TermsOfContentItem(
            right: '<options=bold;fg='.$this->getHttpMethodColor($method).'>'.$method."</> $route->uri".($stats ? " <fg=red>$stats</>" : ''),
            left: $this->getRouteAction($route),
        );

        $tocComponent->render($this->output);
        $this->line('');
    }

    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    private function renderSchemaGroupHeader(Collection $diagnostics): void
    {
        $schema = $diagnostics->first()?->context();
        if (! $schema instanceof SchemaContext) {
            return;
        }

        $errorCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)->count();
        $stats = $errorCount.' '.Str::plural('error', $errorCount);

        $right = "Schema $schema->name <fg=red>$stats</>";
        $left = $schema->class
            ? '<fg=gray>'.Str::replace(['App\\Http\\Resources\\', 'App\\'], '', $schema->class).'</>'
            : null;

        (new TermsOfContentItem(right: $right, left: $left))->render($this->output);
        $this->line('');
    }

    private function renderDiagnostic(Diagnostic $diagnostic, int $i): void
    {
        $message = Str::replace(
            'Dedoc\Scramble\Support\Generator\Types\\',
            '',
            $diagnostic->message(),
        );

        $this->line("<options=bold>$i. [{$diagnostic->code()}] {$message}</>");

        $this->renderTable($diagnostic->details());

        if ($location = $diagnostic->codeLocation()) {
            (new Code($location->file, $location->line))->render($this->output);
        }

        $postfixTableRows = [];
        if ($tip = $diagnostic->tip()) {
            $postfixTableRows[] = ['Tip', $tip];
        }
        if ($docs = $diagnostic->docs()) {
            $postfixTableRows[] = ['Docs', $docs];
        }

        $this->renderTable($postfixTableRows);
    }

    /**
     * @param list<array{0: string, 1: string}> $rows
     */
    private function renderTable(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->output->createTable()
            ->setRows(array_map(
                fn (array $row) => ['<fg=gray>'.$row[0].'</>', $row[1]],
                $rows,
            ))
            ->setStyle('compact')
            ->render();
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

    public function getRouteAction(?Route $route): ?string
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
