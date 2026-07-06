<?php

namespace Dedoc\Scramble\Diagnostics\Model;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;

class Md001PendingMigrationsDiagnostic extends AbstractCodedDiagnostic
{
    public static function forModel(string $modelClass, string $table): self
    {
        return new self(
            "Cannot read database schema for model [$modelClass]: table [$table] does not exist. Model attributes will be documented without database column information.",
            DiagnosticSeverity::Warning,
            category: 'Models',
            context: $modelClass,
        );
    }

    public function code(): string
    {
        return 'MD001';
    }

    public function tip(): string
    {
        return 'Run `php artisan migrate` to create the missing table, or ensure Scramble is analyzing against a database with migrations applied.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#md001';
    }
}
