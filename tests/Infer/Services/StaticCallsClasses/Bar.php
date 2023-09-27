<?php

namespace Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses;

class Bar extends Foo
{
    const SOME = 21;

    public string $prop = 'foo';

    public function parentClassFetch()
    {
        return parent::class;
    }

    public function parentConstFetch()
    {
        return parent::SOME;
    }

    public function newParentCall()
    {
        return new parent;
    }
}
