<?php

namespace Dedoc\Scramble\Diagnostics\PhpDoc;

use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\Concerns\HasCodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\WithCodeLocation;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Illuminate\Console\OutputStyle;

class Pd001RedundantTypeAnnotationDiagnostic extends AbstractCodedDiagnostic implements WithCodeLocation
{
    use HasCodeLocation;

    public ?ArrayItemType_ $arrayItemType = null;

    public ?string $arrayItemKey = null;

    public static function fromArrayItemType(ArrayItemType_ $item): self
    {
        $arrayItemKey = (string) ($item->key ?: '*');
        $inferredType = $item->value->toString();

        $location = CodeLocation::fromArrayItemType($item);

        $diagnostic = (new self(
            "Redundant `@var` annotation. `{$arrayItemKey}` is already inferred as `{$inferredType}`.",
            DiagnosticSeverity::Warning,
            category: 'PHPDoc',
            context: $location?->file,
        ))->withLocation($location);

        $diagnostic->arrayItemType = $item;
        $diagnostic->arrayItemKey = $arrayItemKey;

        return $diagnostic;
    }

    public function key(): string
    {
        return parent::key().'|'.($this->arrayItemKey ?: '');
    }

    public function code(): string
    {
        return 'PD001';
    }

    public function tip(): string
    {
        return 'Remove the `@var` type annotation and keep the description, `@format`, `@example`, or other tags if needed. Scramble infers the type from the expression automatically.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#pd001';
    }

    public function render(OutputStyle $style): void
    {
        if (! $this->location) {
            $style->writeln('    '.$this->message);

            return;
        }

        $anchor = '@var';

        (new Code($this->location->file, $this->location->line, linesAfter: 0))
            ->linesBeforeFirst($anchor)
            ->annotate($anchor, "redundant. `$this->arrayItemKey` is already inferred as `".$this->arrayItemType->value->toString()."`.")
            ->render($style);
    }
}
