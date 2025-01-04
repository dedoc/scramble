<?php

namespace Dedoc\Scramble\Tests\Utils;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Support\Type\Type;

class TestAnalysisResult
{
    public function __construct(
        public readonly Type $type,
        public readonly Index $index,
    )
    {
    }
}
