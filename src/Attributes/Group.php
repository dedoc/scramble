<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Groups endpoints.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Group
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,

        /**
         * Determines the ordering of the groups. Groups with the same weight, are sorted
         * by the name (with `SORT_LOCALE_STRING` sorting flag).
         */
        public readonly int $weight = PHP_INT_MAX,
    ) {}
}
