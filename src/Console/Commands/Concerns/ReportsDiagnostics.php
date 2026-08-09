<?php

namespace Dedoc\Scramble\Console\Commands\Concerns;

use Dedoc\Scramble\Console\Commands\Components\Block;
use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Contracts\Diagnostics\CodedDiagnostic;
use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Exceptions\ConsoleRenderable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** @mixin Command */
trait ReportsDiagnostics
{
    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    protected function reportDiagnostics(Collection $diagnostics, bool $reportSuccess = true): int
    {
        $diagnostics->each(function (Diagnostic $d) {
            $this->renderDiagnosticEntry($d);
            $this->line('');
        });

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

    private function renderDiagnosticEntry(Diagnostic $d): void
    {
        if ($d instanceof CodedDiagnostic) {
            $this->renderCodedDiagnostic($d);

            return;
        }

        $msg = $this->formatDiagnosticMessage($d->message());
        (new Block($msg, 2))->render($this->output);

        $exception = $d->toException();
        if ($exception instanceof ConsoleRenderable) {
            $exception->renderInConsole($this->output);
        }
    }

    private function renderCodedDiagnostic(CodedDiagnostic $d): void
    {
        $location = $d instanceof AbstractCodedDiagnostic ? $d->location() : null;
        $annotation = $d instanceof AbstractCodedDiagnostic ? $d->codeAnnotation() : null;

        if ($location) {
            $path = Str::replace(base_path().DIRECTORY_SEPARATOR, '', $location->file);
            $this->line("{$path}:{$location->line}");
            $this->line('');

            (new Code(
                $location->file,
                $location->line,
                linesBefore: $annotation?->linesBefore ?? 2,
                linesAfter: $annotation?->linesAfter ?? 2,
            ))->render($this->output);

            $this->line('');
        }

        $this->renderCodedDiagnosticMessage($d);
    }

    private function renderCodedDiagnosticMessage(CodedDiagnostic $d): void
    {
        $title = $this->formatDiagnosticMessage($d->title());
        $detail = $this->formatDiagnosticMessage($d->message());

        $prefix = '  '.$d->code().'  ';
        $indent = strlen($prefix);

        $this->line($prefix.$title);

        if ($detail !== '' && $detail !== $title) {
            foreach (explode("\n", $detail) as $line) {
                (new Block($line, $indent))->render($this->output);
            }
        }

        if ($d->tip() !== '') {
            $this->line('');
            (new Block("Tip: {$d->tip()}", $indent))->render($this->output);
        }

        $this->line('');
        (new Block("Docs: {$d->documentationUrl()}", $indent))->render($this->output);
    }

    private function formatDiagnosticMessage(string $message): string
    {
        return Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', $message);
    }
}
