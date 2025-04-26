<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class BodyHeader extends Parameter
{
    public function __construct(
        string $name,
        ?string $description = null,
        ?string $type = null,
        ?string $format = null
    ) {
        parent::__construct('body-header', $name, $description, false, false, $type, $format, true, new MissingValue, new MissingValue, []);
    }
}
