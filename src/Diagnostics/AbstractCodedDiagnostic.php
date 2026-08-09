<?php

namespace Dedoc\Scramble\Diagnostics;

use Throwable;

/**
 * Temporary base for legacy coded diagnostics (VR/JR/MD/PD) until they are migrated
 * to the new Diagnostic shape used by SE001.
 */
abstract class AbstractCodedDiagnostic extends AbstractDiagnostic
{
    abstract public function documentationUrl(): string;

    public function __construct(
        string $message,
        DiagnosticSeverity $severity = DiagnosticSeverity::Warning,
        ?Throwable $originException = null,
        ?CodeLocation $codeLocation = null,
        ?string $tip = null,
    ) {
        parent::__construct(
            $severity,
            $message,
            codeLocation: $codeLocation,
            tip: $tip,
            docs: null,
            originException: $originException,
        );
    }

    public function docs(): ?string
    {
        return $this->documentationUrl();
    }

    public function location(): ?CodeLocation
    {
        return $this->codeLocation;
    }

    public function withLocation(?CodeLocation $location): static
    {
        $this->codeLocation = $location;

        return $this;
    }

    public function codeAnnotation(): ?CodeAnnotation
    {
        return null;
    }
}
