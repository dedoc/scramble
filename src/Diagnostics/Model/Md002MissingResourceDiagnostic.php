<?php

namespace Dedoc\Scramble\Diagnostics\Model;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use InvalidArgumentException;
use ReflectionClass;

class Md002MissingResourceDiagnostic extends AbstractDiagnostic
{
    public static function forModel(string $modelClass): self
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException("Model class [$modelClass] does not exist.");
        }

        return new self(
            DiagnosticSeverity::Warning,
            "Cannot find resource class for model `$modelClass`",
            context: new ClassContext($modelClass),
            codeLocation: CodeLocation::fromReflection(new ReflectionClass($modelClass)),
            tip: 'Create a resource class following Laravel resource naming conventions, pass the resource class explicitly, or add the `#[UseResource(...)]` attribute to the model.',
        );
    }

    public function code(): string
    {
        return 'MD002';
    }

    public function shouldRenderCodeSnippet(): bool
    {
        return false;
    }
}
