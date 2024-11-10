<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Allows naming class based schemas for different contexts.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class SchemaName
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $input = null,
    )
    {
    }
}
