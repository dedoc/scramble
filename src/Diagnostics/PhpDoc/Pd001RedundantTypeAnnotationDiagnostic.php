<?php

namespace Dedoc\Scramble\Diagnostics\PhpDoc;

use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Contracts\Diagnostics\WithCodeLocation;
use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\Concerns\HasCodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Illuminate\Console\OutputStyle;

class Pd001RedundantTypeAnnotationDiagnostic extends AbstractCodedDiagnostic implements WithCodeLocation
{
    use HasCodeLocation;

    private const VAR_TAG = '@var';

    public ?ArrayItemType_ $arrayItemType = null;

    public ?string $arrayItemKey = null;

    public static function fromArrayItemType(ArrayItemType_ $item): self
    {
        $arrayItemKey = (string) ($item->key ?: '*');
        $inferredType = $item->value->toString();

        $location = self::findVarTagLocation(CodeLocation::fromArrayItemType($item));

        $diagnostic = (new self(
            'redundant `'.self::VAR_TAG.'` annotation',
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
        return 'Remove `'.self::VAR_TAG.' *`; keep description, `@format`, `@example`, and other annotations.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#pd001';
    }

    protected function renderBody(OutputStyle $style, int $pad): void
    {
        if (! $this->location()) {
            $this->renderMessageBlock($style, $pad);

            return;
        }

        $location = $this->location();

        $this->writeLocationHeader($style);

        (new Code($location->file, $location->line, linesBefore: 0, linesAfter: $this->linesAfterPhpDoc()))
            ->annotate(self::VAR_TAG, "redundant. `$this->arrayItemKey` is inferred as `".$this->arrayItemType->value->toString().'`.')
            ->render($style);
    }

    private static function findVarTagLocation(?CodeLocation $location): ?CodeLocation
    {
        if (! $location || ! is_readable($location->file)) {
            return $location;
        }

        $lines = file($location->file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return $location;
        }

        for ($index = min($location->line - 1, count($lines) - 1); $index >= 0; $index--) {
            if (str_contains($lines[$index], self::VAR_TAG)) {
                return new CodeLocation($location->file, $index + 1);
            }

            if (str_contains($lines[$index], '/**')) {
                return $location;
            }
        }

        return $location;
    }

    private function linesAfterPhpDoc(): int
    {
        if (! ($location = $this->location())) {
            return 0;
        }

        $phpDoc = $this->arrayItemType->getAttribute('docNode') ?: $this->arrayItemType->value->getAttribute('docNode');
        if (! $phpDoc) {
            return 0;
        }

        $line = $phpDoc->getAttribute('sourceLine');
        if (! $line) {
            return 0;
        }

        return $line - $location->line;
    }
}
