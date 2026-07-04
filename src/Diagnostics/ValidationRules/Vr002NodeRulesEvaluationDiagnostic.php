<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Contracts\Diagnostics\WithCodeLocation;
use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\Concerns\HasCodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Illuminate\Console\OutputStyle;
use Throwable;

class Vr002NodeRulesEvaluationDiagnostic extends AbstractCodedDiagnostic implements WithCodeLocation
{
    use HasCodeLocation;

    public static function fromThrowable(Throwable $throwable): self
    {
        return new self($throwable->getMessage(), DiagnosticSeverity::Warning, $throwable);
    }

    public function code(): string
    {
        return 'VR002';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#vr002';
    }

    public function render(OutputStyle $style): void
    {
        if (! $this->location) {
            $style->writeln('    '.$this->message);

            return;
        }

        (new Code($this->location->file, $this->location->line))
//            ->annotate(
//                class_basename($this->resourceClass),
//                "cannot infer resource's model"
//            )
            ->render($style);
    }
}
