<?php

namespace Dedoc\Scramble\Diagnostics\Schema;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;

class Se001SchemaRuleFailedDiagnostic extends AbstractDiagnostic
{
    public static function forSchema(string $message, string $jsonPointer, OpenApiType $schema): self
    {
        /** @var string|null $originFile */
        $originFile = $schema->getAttribute('file');
        /** @var int|null $originLine */
        $originLine = $schema->getAttribute('line');

        return new self(
            DiagnosticSeverity::Error,
            $message,
            context: $originFile && class_exists($originFile) ? new ClassContext($originFile) : null,
            codeLocation: CodeLocation::from($originFile, $originLine),
            openApiLocation: $jsonPointer,
        );
    }

    public function code(): string
    {
        return 'SE001';
    }
}
