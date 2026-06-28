<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class IgnoreParam
{
    /**
     * @param  'query'|'path'|'header'|'cookie'|'body'|null  $in
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $in = null,
    ) {}
}
