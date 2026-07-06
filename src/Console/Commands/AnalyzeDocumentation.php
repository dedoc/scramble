<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
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

        $diagnostics
            ->groupBy(fn (Diagnostic $d) => $d->context() ?: 'General')
            ->sortKeys()
            ->each(function (Collection $contextDiagnostics, string $context) {
                $context = Str::replace(base_path().DIRECTORY_SEPARATOR, '', $context);

                $this->line("<options=bold>{$context}</>");
                $this->line('');

                $contextDiagnostics->each(function (Diagnostic $d) {
                    $d->render($this->output);
                    $this->line('');
                });
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
}
