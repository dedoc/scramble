<?php

namespace Dedoc\Scramble\Diagnostics\JsonResource;

use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Contracts\Diagnostics\WithCodeLocation;
use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\Concerns\HasCodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Illuminate\Console\OutputStyle;

class Jr001UnknownModelDiagnostic extends AbstractCodedDiagnostic implements WithCodeLocation
{
    use HasCodeLocation;

    public ?string $resourceClass = null;

    public static function forResource(string $resourceClass): self
    {
        $location = CodeLocation::fromReflection(new \ReflectionClass($resourceClass));

        $diagnostic = (new self(
            'cannot infer the resource model',
            DiagnosticSeverity::Warning,
            category: 'JSON resources',
            context: $resourceClass,
        ))->withLocation($location);

        $diagnostic->resourceClass = $resourceClass;

        return $diagnostic;
    }

    public function code(): string
    {
        return 'JR001';
    }

    public function tip(): string
    {
        return 'Add a `@mixin`, `@property`, or `@property-read` PHPDoc annotation to the resource class with the wrapped model type, or name the resource following Laravel conventions (e.g. `UserResource` → `App\\Models\\User`).';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#jr001';
    }

    protected function renderBody(OutputStyle $style, int $pad): void
    {
        if (! $this->location() || ! $this->resourceClass) {
            $this->renderMessageBlock($style, $pad);

            return;
        }

        $location = $this->location();

        $this->writeLocationHeader($style);

        (new Code($location->file, $location->line, linesBefore: 0, linesAfter: 0))
            ->annotate(
                class_basename($this->resourceClass),
                "cannot infer resource's model"
            )
            ->render($style);
    }
}
