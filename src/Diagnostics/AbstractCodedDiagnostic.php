<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Contracts\Diagnostics\CodedDiagnostic;

abstract class AbstractCodedDiagnostic extends AbstractDiagnostic implements CodedDiagnostic
{
    public ?CodeLocation $location = null;

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

    public function location(): ?CodeLocation
    {
        return $this->location;
    }

    public function withLocation(?CodeLocation $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function codeAnnotation(): ?CodeAnnotation
    {
        return null;
    }
}
