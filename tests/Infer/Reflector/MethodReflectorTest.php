<?php

use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Dedoc\Scramble\Tests\Infer\Reflector\Files\Foo;

it('gets method code from parent declaration', function () {
    $reflector = MethodReflector::make(Foo::class, 'foo');

    expect($reflector->getMethodCode())->toContain('return 1;');
});

it('gets method ast from declaration if line separator is cr', function () {
    $reflector = MethodReflector::make(Foo::class, 'foo');

    expect($reflector->getAstNode())->not->toBeNull();
});
