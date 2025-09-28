<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;

it('doesnt fail on internal class definition request', function () {
    $index = new Index;

    $def = $index->getClass(\Error::class);

    expect($def)->toBeInstanceOf(ClassDefinition::class);
});

class Bar_IndexTest
{
    public function foo(): int {}
}

beforeEach(function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            Bar_IndexTest::class,
            BarGeneric_IndexTest::class,
        ]);
});

class Foo_IndexTest extends Bar_IndexTest {}
it('can get primitive type from non-ast analyzable class', function () {
    $type = getStatementType('(new '.Foo_IndexTest::class.')->foo()');

    expect($type->toString())->toBe('int');
});

/** @template T */
class BarGeneric_IndexTest
{
    /** @return T */
    public function foo() {}
}
it('can get generic type from non-ast analyzable class', function () {
    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new MethodCallReferenceType(
                new Generic(BarGeneric_IndexTest::class, [new StringType]),
                'foo',
                [],
            ),
        );

    expect($type->toString())->toBe('string');
});

/** @extends BarGeneric_IndexTest<string> */
class FooGeneric_IndexTest extends BarGeneric_IndexTest {}
it('can get generic type from extended non-ast analyzable class', function () {
    $type = getStatementType('(new '.FooGeneric_IndexTest::class.')->foo()');

    expect($type->toString())->toBe('string');
});
