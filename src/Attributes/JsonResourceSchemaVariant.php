<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Defines a named OpenAPI schema variant for a JsonResource based on loaded Eloquent relations.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class JsonResourceSchemaVariant
{
    /**
     * @param  list<string>|'*'  $withLoaded
     */
    public function __construct(
        public readonly string $name,
        public readonly array|string $withLoaded = [],
        public readonly bool $fallback = false,
    ) {}
}
