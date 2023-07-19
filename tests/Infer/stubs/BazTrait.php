<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

trait BazTrait
{
    public $propBaz;

    public function methodBaz()
    {
        return 1;
    }

    public function methodInvokingFooTraitMethod()
    {
        return $this->something();
    }
}
