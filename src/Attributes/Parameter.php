<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class Parameter
{
    public readonly bool $required;

    /**
     * @param  'query'|'path'|'header'|'cookie'|'body'|'body-header'  $in
     * @param  scalar|array|object|MissingValue  $example
     * @param  array<string, Example>  $examples  The key is a distinct name and the value is an example object.
     */
    public function __construct(
        public readonly string $in,
        public readonly string $name,
        public readonly ?string $description = null,
        ?bool $required = null,
        public bool $deprecated = false,
        public ?string $type = null,
        public ?string $format = null,
        public bool $infer = true,
        public mixed $default = new MissingValue,
        public mixed $example = new MissingValue,
        public array $examples = [],
    ) {
        $this->required = $required !== null ? $required : $this->in === 'path';
    }
}
