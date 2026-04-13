<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Enums\JsonApiArraySerialization;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class JsonApiConfig
{
    public function __construct(
        public readonly JsonApiArraySerialization $arraySerialization = JsonApiArraySerialization::Comma,
        public readonly ?int $maxRelationshipDepth = null,
    ) {}

    /** @return non-negative-int */
    public function maxRelationshipDepth(): int
    {
        if ($this->maxRelationshipDepth === null) {
            return JsonApiResource::$maxRelationshipDepth;
        }

        return max(0, min($this->maxRelationshipDepth, JsonApiResource::$maxRelationshipDepth));
    }
}
