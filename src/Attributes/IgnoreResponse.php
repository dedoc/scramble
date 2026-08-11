<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Excludes responses with the given status from the generated documentation.
 *
 * Status may be an exact HTTP code (`200`, `"default"`) or a mask (`"30*"`).
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class IgnoreResponse
{
    public function __construct(
        public readonly int|string $status,
    ) {}

    public function matches(int|string|null $status): bool
    {
        $actual = (string) ($status ?? 'default');
        $ignored = (string) $this->status;

        if (! str_contains($ignored, '*')) {
            return $actual === $ignored;
        }

        return (bool) preg_match(
            '/^'.str_replace('\*', '.*', preg_quote($ignored, '/')).'$/',
            $actual,
        );
    }
}
