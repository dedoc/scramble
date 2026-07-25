<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Declares eager-loaded relations for a JsonResource returned by an endpoint.
 *
 * Applied wherever the resource appears in the response type tree — alone,
 * inside collections, paginators, or nested resource wrappers.
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class WithRelations
{
    /**
     * @param  class-string<JsonResource>  $class
     * @param  list<string>  $relations
     */
    public function __construct(
        public readonly string $class,
        public readonly array $relations,
    ) {}
}
