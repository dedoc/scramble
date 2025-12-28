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
        /**
         * Allows to override the method of the endpoint in the documentation. Useful when you want to document the
         * `PUT|PATCH` endpoint as `PATCH`. The method provided here MUST be the actual method the API will reply to.
         */
        public readonly ?string $method = null,
    ) {}
}
