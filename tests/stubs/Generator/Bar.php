<?php

namespace Dedoc\Scramble\Tests\stubs\Generator;

class Bar
{
    public function getResponse()
    {
        return (new Other)->getResponse();
    }
}
