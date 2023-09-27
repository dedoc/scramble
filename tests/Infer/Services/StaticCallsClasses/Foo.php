<?php

namespace Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses;

class Foo
{
    public function selfClassFetch()
    {
        return self::class;
    }

    public function classConst()
    {
        return __CLASS__;
    }

    public function staticClassFetch()
    {
        return static::class;
    }
}
