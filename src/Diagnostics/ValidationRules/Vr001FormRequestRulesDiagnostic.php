<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use ReflectionClass;
use Throwable;

class Vr001FormRequestRulesDiagnostic extends AbstractDiagnostic
{
    public static function fromThrowableAndReflection(Throwable $throwable, ReflectionClass $reflectionClass): self
    {
        $location = ($file = $throwable->getFile())
            ? CodeLocation::from($file, $throwable->getLine())
            : CodeLocation::fromReflection($reflectionClass);

        return new self(
            DiagnosticSeverity::Warning,
            class_basename($reflectionClass->getName()).'::rules() call failed',
            codeLocation: $location,
            tip: 'Scramble evaluates rules() outside the normal request lifecycle, so some values may be unavailable. Make such access safe when appropriate, for example: $this->user()?->company_id, $this->route(\'param\').',
            originException: $throwable,
        );
    }

    public function code(): string
    {
        return 'VR001';
    }

    public function details(): array
    {
        return [
            ...parent::details(),
            ['Message', $this->originException->getMessage()],
        ];
    }
}
