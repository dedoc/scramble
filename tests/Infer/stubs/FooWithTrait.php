<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class FooWithTrait
{
    use BazTrait;

    public function something()
    {
        return 'wow';
    }
}
