<?php

namespace Dedoc\Scramble\Diagnostics\PhpDoc;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Support\Type\ArrayItemType_;

class Pd001RedundantTypeAnnotationDiagnostic extends AbstractDiagnostic
{
    private const VAR_TAG = '@var';

    private string $arrayItemKey;

    public static function fromArrayItemType(ArrayItemType_ $item): self
    {
        $arrayItemKey = (string) ($item->key ?: '*');
        $inferredType = $item->value->toString();
        $location = self::findVarTagLocation(CodeLocation::fromArrayItemType($item));

        /** @var \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode|null $docNode */
        $docNode = $item->getAttribute('docNode') ?: $item->value->getAttribute('docNode');
        $sourceClass = $docNode?->getAttribute('sourceClass');

        $diagnostic = new self(
            DiagnosticSeverity::Warning,
            'Redundant `'.self::VAR_TAG.'` annotation for `'.$arrayItemKey.'`',
            context: is_string($sourceClass) ? new ClassContext($sourceClass) : null,
            codeLocation: $location,
            tip: 'Remove `'.self::VAR_TAG.'`; the type is already inferred as `'.$inferredType.'`',
        );
        $diagnostic->arrayItemKey = $arrayItemKey;

        return $diagnostic;
    }

    public function code(): string
    {
        return 'PD001';
    }

    public function key(): string
    {
        return parent::key().'|'.$this->arrayItemKey;
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
}
