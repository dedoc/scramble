<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\Schema\Se001SchemaRuleFailedDiagnostic;
use Dedoc\Scramble\Exceptions\InvalidSchema;
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
     * @return array{list<Diagnostic>, ?InvalidSchema}
     */
    public function validate(OpenApiType $type, string $path): array
    {
        $diagnostics = [];
        $exception = null;

        foreach ($this->rules as [$ruleCb, $errorMessageGetter, $ignorePaths, $throw]) {
            if (Str::is($ignorePaths, $path)) {
                continue;
            }

            if ($ruleCb($type, $path)) {
                continue;
            }

            $message = value($errorMessageGetter, $type, $path);

            $diagnostics[] = Se001SchemaRuleFailedDiagnostic::forSchema(
                message: $message,
                jsonPointer: $path,
                schema: $type,
            );

            if ($throw && ! $exception) {
                $exception = InvalidSchema::createForSchema(
                    $message,
                    $path,
                    $type->getAttribute('file'),
                    $type->getAttribute('line'),
                );
            }
        }

        return [$diagnostics, $exception];
    }
}
