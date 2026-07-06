<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Console\Commands\Components\Block;
use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Contracts\Diagnostics\CodedDiagnostic;
use Dedoc\Scramble\Contracts\Diagnostics\WithCodeLocation;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;

abstract class AbstractCodedDiagnostic extends AbstractDiagnostic implements CodedDiagnostic
{
    abstract public function code(): string;

    abstract public function documentationUrl(): string;

    public function tip(): string
    {
        return '';
    }

    public function key(): string
    {
        return $this->code().'|'.($this->context() ?: '');
    }

    protected static function defaultContext(): ?string
    {
        return null;
    }

    public function context(): ?string
    {
        return $this->context ?? static::defaultContext();
    }

    public function render(OutputStyle $style): void
    {
        $pad = 4;

        $this->renderBody($style, $pad);

        $style->writeln('');

        if ($this->tip() !== '') {
            (new Block("Tip: {$this->tip()}", $pad))->render($style);
        }

        (new Block("Docs: {$this->documentationUrl()}", $pad))->render($style);
    }

    protected function renderBody(OutputStyle $style, int $pad): void
    {
        if ($this instanceof WithCodeLocation && ($location = $this->location())) {
            $this->writeLocationHeader($style);
            (new Code($location->file, $location->line))->render($style);

            return;
        }

        $this->renderMessageBlock($style, $pad);
    }

    protected function writeLocationHeader(OutputStyle $style): void
    {
        if (! $this instanceof WithCodeLocation || ! ($location = $this->location())) {
            return;
        }

        $style->writeln("    --> line {$location->line} [{$this->code()}]: {$this->message()}");
    }

    protected function renderMessageBlock(OutputStyle $style, int $pad): void
    {
        $message = Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', $this->message());
        $lines = explode("\n", $message);
        $first = Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', $lines[0]);
        $continuationLines = array_slice($lines, 1);

        (new Block(
            "<options=bold>[{$this->code()}] {$first}</>",
            $pad,
        ))->render($style);

        foreach ($continuationLines as $line) {
            (new Block($line, $pad))->render($style);
        }
    }
}
