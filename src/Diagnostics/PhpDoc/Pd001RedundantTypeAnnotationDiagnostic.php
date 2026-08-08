<?php

namespace Dedoc\Scramble\Diagnostics\PhpDoc;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeAnnotation;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Support\Type\ArrayItemType_;

class Pd001RedundantTypeAnnotationDiagnostic extends AbstractCodedDiagnostic
{
    private const VAR_TAG = '@var';

    public function __construct(
        public readonly string $arrayItemKey,
        public readonly string $inferredType,
        public readonly int $linesAfter,
        ?string $context = null,
    ) {
        parent::__construct(
            'redundant `'.self::VAR_TAG.'` annotation',
            DiagnosticSeverity::Warning,
            category: 'PHPDoc',
            context: $context,
        );
    }

    public static function fromArrayItemType(ArrayItemType_ $item): self
    {
        $arrayItemKey = (string) ($item->key ?: '*');
        $location = self::findVarTagLocation(CodeLocation::fromArrayItemType($item));

        return (new self(
            arrayItemKey: $arrayItemKey,
            inferredType: $item->value->toString(),
            linesAfter: self::linesAfterPhpDoc($item, $location),
            context: $location?->file,
        ))->withLocation($location);
    }

    public function codeAnnotation(): CodeAnnotation
    {
        return new CodeAnnotation(
            anchor: self::VAR_TAG,
            message: "redundant. `$this->arrayItemKey` is inferred as `$this->inferredType`.",
            linesBefore: 0,
            linesAfter: $this->linesAfter,
        );
    }

    public function key(): string
    {
        return parent::key().'|'.$this->arrayItemKey;
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

    private static function linesAfterPhpDoc(ArrayItemType_ $item, ?CodeLocation $location): int
    {
        if (! $location) {
            return 0;
        }

        $phpDoc = $item->getAttribute('docNode') ?: $item->value->getAttribute('docNode');
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
