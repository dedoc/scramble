<?php

namespace Dedoc\Scramble\Diagnostics\Schema;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use ReflectionClass;

class Se002InvalidDiscriminatorMappingDiagnostic extends AbstractDiagnostic
{
    /**
     * @param  class-string  $className
     */
    public static function forMappedType(string $className, mixed $mappedType): self
    {
        return new self(
            DiagnosticSeverity::Warning,
            sprintf(
                'Cannot document [%s] from the discriminator mapping',
                is_string($mappedType) ? $mappedType : get_debug_type($mappedType),
            ),
            context: new ClassContext($className),
            codeLocation: CodeLocation::fromReflection(new ReflectionClass($className)),
            tip: 'The `#[Discriminator]` attribute mapping must consist of the names of existing classes or interfaces.',
        );
    }

    public function code(): string
    {
        return 'SE002';
    }

    public function shouldRenderCodeSnippet(): bool
    {
        return false;
    }
}
