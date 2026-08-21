<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;
use Illuminate\Support\Collection;

/**
 * Adapts documentation cached by older Scramble versions to GeneratorResult.
 *
 * This backward-compatibility layer will be removed in a later release after
 * users have had time to rebuild their cache with `scramble:cache`.
 *
 * @internal
 */
class OldGeneratorResult extends GeneratorResult
{
    /** @param array<mixed, mixed> $oldSpec */
    public function __construct(
        private array $oldSpec,
    ) {}

    public function spec(): array
    {
        return $this->oldSpec;
    }

    public function openApi(): OpenApi
    {
        return new OpenApi('3.1.0');
    }

    public function diagnostics(): Collection
    {
        return collect([$this->staleCacheDiagnostic()]);
    }

    public function proNudge(): ProNudgeCollector
    {
        return new ProNudgeCollector;
    }

    private function staleCacheDiagnostic(): Diagnostic
    {
        return new GenericDiagnostic(
            DiagnosticSeverity::Warning,
            'A stale documentation cache is being used. Run `php artisan scramble:cache` to rebuild it',
        );
    }
}
