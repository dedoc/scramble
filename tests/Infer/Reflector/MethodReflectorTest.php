<?php

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Dedoc\Scramble\Tests\Infer\Reflector\Files\Foo;
use Dedoc\Scramble\Tests\Infer\Reflector\Files\PostController;

it('gets method code from parent declaration', function () {
    $reflector = MethodReflector::make(Foo::class, 'foo');

    expect($reflector->getMethodCode())->toContain('return 1;');
});

it('gets method ast from parent declaration', function () {
    $reflector = ClassReflector::make(PostController::class)->getMethod('index');

    dd($reflector->getAstNode())->not->toBeNull();
});
