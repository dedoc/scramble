<?php

namespace Dedoc\Scramble\Diagnostics\Schema;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;

class Se001SchemaRuleFailedDiagnostic extends AbstractDiagnostic
{
    /** Raw `file` attribute from the schema (class name or path), for exception messaging. */
    private ?string $originFile = null;

    public static function forSchema(string $message, string $jsonPointer, OpenApiType $schema): self
    {
        /** @var string|null $originFile */
        $originFile = $schema->getAttribute('file');
        /** @var int|null $originLine */
        $originLine = $schema->getAttribute('line');

        $diagnostic = new self(
            DiagnosticSeverity::Error,
            $message,
            context: $originFile && class_exists($originFile) ? new ClassContext($originFile) : null,
            codeLocation: CodeLocation::from($originFile, $originLine),
            openApiLocation: $jsonPointer,
        );
        $diagnostic->originFile = is_string($originFile) ? $originFile : null;

        return $diagnostic;
    }

    public function code(): string
    {
        return 'SE001';
    }

    public function originFile(): ?string
    {
        return $this->originFile;
    }

    public function openApiLocation(): string
    {
        return $this->openApiLocation ?? '';
    }
}
