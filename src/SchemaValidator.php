<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\Schema\Se001SchemaRuleFailedDiagnostic;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Illuminate\Support\Str;

class SchemaValidator
{
    /**
     * @param  array<int, array{callable(OpenApiType, string): bool, (callable(OpenApiType, string): string)|string, array<string>, bool}>  $rules
     */
    public function __construct(
        private array $rules,
    ) {}

    public function hasRules(): bool
    {
        return (bool) count($this->rules);
    }

    /**
     * @return list<Diagnostic>
     */
    public function validate(OpenApiType $type, string $path): array
    {
        $diagnostics = [];

        foreach ($this->rules as [$ruleCb, $errorMessageGetter, $ignorePaths, $throw]) {
            if (Str::is($ignorePaths, $path)) {
                continue;
            }

            if ($ruleCb($type, $path)) {
                continue;
            }

            $diagnostics[] = Se001SchemaRuleFailedDiagnostic::forSchema(
                message: value($errorMessageGetter, $type, $path),
                jsonPointer: $path,
                schema: $type,
            )->withSeverity($throw ? DiagnosticSeverity::Error : DiagnosticSeverity::Warning);
        }

        return $diagnostics;
    }
}
