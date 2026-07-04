<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class QueryParameter extends Parameter
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
        public readonly ?QueryParameterStyle $style = null,
        public readonly ?bool $explode = null,
        public readonly ?bool $allowReserved = null,
    ) {
        parent::__construct('query', $name, $description, $required, $deprecated, $type, $format, $infer, $default, $example, $examples);
    }
}
