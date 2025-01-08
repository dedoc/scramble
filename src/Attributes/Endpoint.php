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
         * by the operationId (with `SORT_LOCALE_STRING` sorting flag).
         */
        public readonly int $weight = INF,
    )
    {
    }
}
