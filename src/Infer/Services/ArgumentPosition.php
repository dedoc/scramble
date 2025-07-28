<?php

namespace Dedoc\Scramble\Infer\Services;

class ArgumentPosition
{
    public function __construct(
        public readonly int $index,
        public readonly ?string $name = null,
    ) {}
}
