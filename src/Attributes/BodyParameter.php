<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE|Attribute::TARGET_ALL)]
class BodyParameter
{
    /**
     * @param scalar|array|object|MissingValue $example
     * @param array<string, Example> $examples The key is a distinct name and the value is an example object.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly bool $required = false,
        public bool $deprecated = false,
        public ?string $type = null,
        public bool $infer = true,
        public mixed $default = new MissingValue,
        public mixed $example = new MissingValue,
        public array $examples = [],
    )
    {
    }
}
