<?php

use Dedoc\Scramble\Support\Type\KeyedArrayType;

it('infers keyed array shape type', function () {
    expect($type = getStatementType("['foo' => 1, 'bar' => 'foo', 23]"))
        ->toBeInstanceOf(KeyedArrayType::class)
        ->and($type->toString())
        ->toBe('array{foo: int(1), bar: string(foo), 0: int(23)}');
});

it('infers list type', function () {
    expect($type = getStatementType("[1, 2, 'foo']"))
        ->toBeInstanceOf(KeyedArrayType::class)
        ->and($type->isList)
        ->toBeTrue()
        ->and($type->toString())
        ->toBe('list{int(1), int(2), string(foo)}');
});

it('infers array spread in resulting type', function () {
    expect(getStatementType("[42, 'b' => 'foo', ...['a' => 1, 'b' => 'wow', 16], 23]")->toString())
        ->toBe('array{0: int(42), b: string(wow), a: int(1), 1: int(16), 2: int(23)}');
});

// @todo: Move test to reference resolving tests group
it('infers array spread from other methods', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return ['b' => 'foo', ['c' => 'w', ...$this->bar()]];
    }
    public function bar () {
        return ['a' => 123];
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())
        ->toBe('(): array{b: string(foo), 0: array{c: string(w), a: int(123)}}');
});
