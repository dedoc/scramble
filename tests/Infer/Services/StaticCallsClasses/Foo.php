<?php

namespace Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses;

class Foo
{
    const SOME = 42;

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

    public function selfConstFetch()
    {
        return self::SOME;
    }

    public function staticConstFetch()
    {
        return static::SOME;
    }

    public function newSelfCall()
    {
        return new self;
    }

    public function newStaticCall()
    {
        return new static;
    }
}
