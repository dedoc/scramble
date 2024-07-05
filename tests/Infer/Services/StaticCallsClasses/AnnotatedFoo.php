<?php

namespace Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses;

class AnnotatedFoo
{
    public function fooMethod(): self
    {
        return $this;
    }

    public function build()
    {
        return ['from' => 'foo'];
    }
}
