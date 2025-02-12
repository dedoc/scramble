<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class CookieParameter extends Parameter
{
    public function __construct(
        string $name,
        ?string $description = null,
        ?bool $required = null,
        $deprecated = false,
        ?string $type = null,
        ?string $format = null,
        bool $infer = true,
        mixed $default = new MissingValue,
        mixed $example = new MissingValue,
        array $examples = [],
    ) {
        parent::__construct('cookie', $name, $description, $required, $deprecated, $type, $format, $infer, $default, $example, $examples);
    }
}
