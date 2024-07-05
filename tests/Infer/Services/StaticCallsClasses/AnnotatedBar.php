<?php

namespace Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses;

class AnnotatedBar extends AnnotatedFoo
{
    public function build()
    {
        return ['from' => 'bar'];
    }
}
