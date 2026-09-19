<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Declares a tag without putting the annotated endpoints in it.
 *
 * `Group` states where an endpoint belongs; this states what a tag is. It exists
 * for the tag nothing else declares: a parent named by `Group::$parent` holds no
 * endpoints of its own, so without this it reaches the document as a bare name.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Tag
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,

        /**
         * Determines the ordering of the tags. Tags with the same weight, are sorted
         * by the name (with `SORT_LOCALE_STRING` sorting flag).
         */
        public readonly int $weight = PHP_INT_MAX,

        /**
         * The name of a tag this one is nested under, so a parent may itself have a parent.
         */
        public readonly ?string $parent = null,

        /**
         * A short summary of the tag.
         */
        public readonly ?string $summary = null,

        /**
         * A machine-readable category for the tag. Any string is allowed;
         * the spec names `nav`, `badge` and `audience` as common values.
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
