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

        /**
         * The parent tag name. Used to organize tags into hierarchical groups
         * via the native OpenAPI 3.2.0 Tag Object `parent` field.
         */
        public readonly ?string $parent = null,

        /**
         * A short summary of the tag.
         */
        public readonly ?string $summary = null,

        /**
         * The kind of tag: "navigation" (default) or "api".
         */
        public readonly ?string $kind = null,

        /**
         * URL for additional external documentation for this tag.
         */
        public readonly ?string $externalDocsUrl = null,

        /**
         * Description of the external documentation for this tag.
         */
        public readonly ?string $externalDocsDescription = null,
    ) {}
}
