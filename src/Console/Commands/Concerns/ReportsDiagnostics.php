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
        $pad = 4;

        if ($d instanceof CodedDiagnostic) {
            $this->renderCodedDiagnostic($d, $pad);

            return;
        }

        $msg = $this->formatDiagnosticMessage($d->message());
        (new Block($msg, $pad))->render($this->output);

        $exception = $d->toException();
        if ($exception instanceof ConsoleRenderable) {
            $exception->renderInConsole($this->output);
        }
    }

    private function renderCodedDiagnostic(CodedDiagnostic $d, int $pad): void
    {
        $location = $d instanceof AbstractCodedDiagnostic ? $d->location() : null;
        $annotation = $d instanceof AbstractCodedDiagnostic ? $d->codeAnnotation() : null;

        if ($location && $annotation) {
            $this->output->writeln('    --> line '.$location->line.' ['.$d->code().']: '.$d->message());

            (new Code(
                $location->file,
                $location->line,
                linesBefore: $annotation->linesBefore,
                linesAfter: $annotation->linesAfter,
            ))
                ->annotate($annotation->anchor, $annotation->message)
                ->render($this->output);
        } elseif ($location) {
            $this->output->writeln('    --> line '.$location->line.' ['.$d->code().']: '.$d->message());

            (new Code($location->file, $location->line))->render($this->output);
        } else {
            $message = $this->formatDiagnosticMessage($d->message());
            $lines = explode("\n", $message);
            $first = $this->formatDiagnosticMessage($lines[0]);

            (new Block(
                "<options=bold>[{$d->code()}] {$first}</>",
                $pad,
            ))->render($this->output);

            foreach (array_slice($lines, 1) as $line) {
                (new Block($line, $pad))->render($this->output);
            }
        }

        $this->output->writeln('');

        if ($d->tip() !== '') {
            (new Block("Tip: {$d->tip()}", $pad))->render($this->output);
        }

        (new Block("Docs: {$d->documentationUrl()}", $pad))->render($this->output);
    }

    private function formatDiagnosticMessage(string $message): string
    {
        return Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', $message);
    }
}
