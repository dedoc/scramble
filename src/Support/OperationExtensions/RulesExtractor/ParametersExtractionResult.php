<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;

/**
 * @internal
 */
class ParametersExtractionResult
{
    /**
     * @param Parameter[] $parameters
     * @param string|null $schemaName
     * @param string|null $description
     */
    public function __construct(
        public array $parameters,
        public ?string $schemaName = null,
        public ?string $description = null,
    ) {}
}
