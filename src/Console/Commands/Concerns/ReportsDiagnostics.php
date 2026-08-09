<?php

namespace Dedoc\Scramble\Console\Commands\Concerns;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @mixin Command
 *
 * @todo rewrite against the new Diagnostic contract / analyze renderer
 */
trait ReportsDiagnostics
{
    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    protected function reportDiagnostics(Collection $diagnostics, bool $reportSuccess = true): int
    {
        $errorCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Error)->count();
        $warningCount = $diagnostics->filter(fn (Diagnostic $d) => $d->severity() === DiagnosticSeverity::Warning)->count();

        if ($errorCount > 0) {
            $this->error($this->formatDiagnosticsSummary($errorCount, $warningCount, isError: true));

            return static::FAILURE;
        }

        if ($warningCount > 0) {
            $this->warn($this->formatDiagnosticsSummary($errorCount, $warningCount, isError: false));

            return static::SUCCESS;
        }

        if ($reportSuccess) {
            $this->info('Everything is fine! Documentation is generated without any errors 🍻');
        }

        return static::SUCCESS;
    }

    private function formatDiagnosticsSummary(int $errors, int $warnings, bool $isError): string
    {
        $errorLabel = $errors.' '.Str::plural('error', $errors);
        $warningLabel = $warnings.' '.Str::plural('warning', $warnings);
        $bracket = $isError ? 'ERROR' : 'WARNING';

        return "[$bracket] Found $errorLabel, $warningLabel.";
    }
}
