<?php

namespace Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses;

class Bar extends Foo
{
    public function parentClassFetch()
    {
        return parent::class;
    }
}
