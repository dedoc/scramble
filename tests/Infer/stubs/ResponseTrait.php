<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

use Illuminate\Http\Response;

trait ResponseTrait
{
    public function foo()
    {
        return Response::HTTP_CONTINUE;
    }
}
