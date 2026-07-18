<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Restricts a route to one or more registered APIs.
 *
 * Routes without this attribute are not further restricted and continue to follow
 * existing route resolver / filtering rules.
 *
 * Method-level attributes override class-level attributes.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Api
{
    /**
     * @var list<string>
     */
    public readonly array $only;

    public function __construct(string|array $only)
    {
        $this->only = is_array($only) ? array_values($only) : [$only];
    }
}
