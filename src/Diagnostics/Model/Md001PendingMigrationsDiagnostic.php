<?php

namespace Dedoc\Scramble\Diagnostics\Model;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use ReflectionClass;

class Md001PendingMigrationsDiagnostic extends AbstractDiagnostic
{
    public static function forModel(string $modelClass, string $table): self
    {
        return new self(
            DiagnosticSeverity::Warning,
            "Cannot read database schema for `$modelClass`: table `$table` does not exist",
            context: new ClassContext($modelClass),
            codeLocation: CodeLocation::fromReflection(new ReflectionClass($modelClass)),
            tip: 'Run `php artisan migrate`, or use a database with the required migrations applied. Without the table, Scramble cannot use database column information for model attributes.',
        );
    }

    public function code(): string
    {
        return 'MD001';
    }

    public function shouldRenderCodeSnippet(): bool
    {
        return false;
    }
}
