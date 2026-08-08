<?php

namespace Dedoc\Scramble\Diagnostics;

class CodeAnnotation
{
    public function __construct(
        public readonly string $anchor,
        public readonly string $message,
        public readonly int $linesBefore = 2,
        public readonly int $linesAfter = 2,
    ) {}
}
