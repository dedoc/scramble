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
        /**
         * Some classes can be used both as input and output schemas. So this property is used to
         * explicitly name the schema when is in input context.
         */
        public readonly ?string $input = null,
    ) {}
}
