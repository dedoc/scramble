<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Adds metadata to endpoints.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Endpoint
{
    public function __construct(
        /**
         * Determines the ordering of the endpoints. Endpoints with the same weight, are sorted
         * by the order of their declaration.
         */
        public readonly int $weight = INF,
    ) {}
}
