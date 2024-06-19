<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

class ValidationNodesResult
{
    public function __construct(
        public $node,
        public ?string $schemaName = null,
        public ?string $description = null,
    ) {}
}
