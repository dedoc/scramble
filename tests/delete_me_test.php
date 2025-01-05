<?php

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Reflector\MethodReflector;

it('lalala', function () {
    $parser = (new PhpParser\ParserFactory)->createForHostVersion();

    //    \Illuminate\Support\Benchmark::dd(fn () => $parser->parse(
    // //        file_get_contents((new ReflectionClass(\Dedoc\Scramble\Tests\Files\Sample::class))->getFileName())
    //        file_get_contents((new ReflectionClass(\Illuminate\Database\Eloquent\Model::class))->getFileName())
    //    ), 10);

    //    \Illuminate\Support\Benchmark::dd(fn () => //$parser->parse(
    // //        file_get_contents((new ReflectionClass(\Dedoc\Scramble\Tests\Files\Sample::class))->getFileName())
    //        new ReflectionClass(\Illuminate\Database\Eloquent\Model::class)
    //    , 10);

    $path = ClassReflector::make(\Illuminate\Database\Eloquent\Model::class)->getReflection()->getFileName();
    $methods = ClassReflector::make(\Illuminate\Database\Eloquent\Model::class)->getReflection()->getMethods();

    //    \Illuminate\Support\Benchmark::dd(
    //        fn () => $parser->parse(file_get_contents($path)),
    //        1,
    //    );
    \Illuminate\Support\Benchmark::dd(
        fn () => array_map(fn (\ReflectionMethod $m) => MethodReflector::make(\Illuminate\Database\Eloquent\Model::class, $m->name)->getAstNode(), $methods),
        1,
    );

    $a = 1;
});
