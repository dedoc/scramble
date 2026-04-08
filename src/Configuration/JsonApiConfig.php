<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Enums\JsonApiArraySerialization;

class JsonApiConfig
{
    public function __construct(
        public readonly JsonApiArraySerialization $arraySerialization = JsonApiArraySerialization::Comma,
    ) {}
}
