<?php

namespace Dedoc\Scramble\Tests\Infer\Analyzer;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('analyzes exceptions annotations on method call', function (string $class) {
    $definition = (new ClassAnalyzer($index = new Index))
        ->analyze($class)
        ->getMethodDefinition(
            'foo',
            scope: new GlobalScope($index),
            withSideEffects: true,
        );

    expect($definition->type->exceptions)
        ->toHaveCount(1)
        ->and($definition->type->exceptions[0]->name)
        ->toBe(HttpException::class);
})->with([
    [MethodCall_MethodAnalyzerTest::class],
    [MethodCallOnProperty_MethodAnalyzerTest::class],
    [MethodCallOnParameter_MethodAnalyzerTest::class],
    [StaticMethodCall_MethodAnalyzerTest::class],
    [StaticMethodCallSelf_MethodAnalyzerTest::class],
]);

class MethodCall_MethodAnalyzerTest
{
    public function foo(): void
    {
        $this->bar();
    }

    /**
     * @throws HttpException
     */
    public function bar() {}
}

class MethodCallOnProperty_MethodAnalyzerTest
{
    public MethodCallOnProperty_MethodAnalyzerTest $item;

    public function foo(): void
    {
        $this->item->bar();
    }

    /**
     * @throws HttpException
     */
    public function bar() {}
}

class MethodCallOnParameter_MethodAnalyzerTest
{
    public function foo(MethodCallOnParameter_MethodAnalyzerTest $item): void
    {
        $item->bar();
    }

    /**
     * @throws HttpException
     */
    public function bar() {}
}

class StaticMethodCall_MethodAnalyzerTest
{
    public static function foo(): void
    {
        StaticMethodCall_MethodAnalyzerTest::bar();
    }

    /**
     * @throws HttpException
     */
    public static function bar() {}
}

class StaticMethodCallSelf_MethodAnalyzerTest
{
    public static function foo(): void
    {
        self::bar();
    }

    /**
     * @throws HttpException
     */
    public static function bar() {}
}
