<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Scramble;

it('doesnt fail on internal class definition request', function () {
    $index = new Index;

    $def = $index->getClass(\Error::class);

    expect($def)->toBeInstanceOf(ClassDefinition::class);
});

class Bar_IndexTest
{
    public function foo(): int {}
}

class Foo_IndexTest extends Bar_IndexTest {}
it('can get primitive type from non-ast analyzable class', function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([Bar_IndexTest::class]);

    $type = getStatementType('(new '.Foo_IndexTest::class.')->foo()');

    expect($type->toString())->toBe('int');
});
