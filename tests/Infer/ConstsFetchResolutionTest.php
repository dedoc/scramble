<?php

namespace Dedoc\Scramble\Tests\Infer;

use Dedoc\Scramble\Tests\Infer\stubs\ResponseTrait;

class Bar_Consts
{
    use ResponseTrait;
}

it('infers a return type of trait method', function () {
    $type = getStatementType('(new \Dedoc\Scramble\Tests\Infer\Bar_Consts)->foo()');

    expect($type->toString())->toBe('int(100)');
});
