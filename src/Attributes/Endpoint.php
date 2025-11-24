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
         * Assigns an OperationID to a controller method.
         */
        public readonly ?string $operationId = null,
        /**
         * Sets the title (summary) of the endpoint.
         */
        public readonly ?string $title = null,
        /**
         * Sets the description of the endpoint.
         */
        public readonly ?string $description = null,
    ) {}
}
