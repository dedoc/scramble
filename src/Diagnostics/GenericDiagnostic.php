<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Console\Commands\Components\Block;
use Dedoc\Scramble\Diagnostics\ValidationRules\Vr003AllEvaluatorsFailedDiagnostic;
use Dedoc\Scramble\Exceptions\ConsoleRenderable;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use Throwable;

class GenericDiagnostic extends AbstractDiagnostic
{
    public function key(): string
    {
        return $this->context() ?: '';
    }

    public function render(OutputStyle $style): void
    {
        $pad = 4;
        $msg = Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', $this->message());
        (new Block($msg, $pad))->render($style);

        $exception = $this->toException();
        if ($exception instanceof ConsoleRenderable) {
            $exception->renderInConsole($style);
        }
    }

    public static function fromException(Throwable $exception): self|Vr003AllEvaluatorsFailedDiagnostic
    {
        if ($exception instanceof RulesEvaluationException) {
            return Vr003AllEvaluatorsFailedDiagnostic::fromRulesEvaluationException($exception);
        }

        return new self(
            $exception->getMessage(),
            DiagnosticSeverity::Error,
            $exception,
        );
    }
}
