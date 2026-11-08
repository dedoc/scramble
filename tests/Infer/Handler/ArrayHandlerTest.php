<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
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

it('infers array spread from other methods #1026', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo(): array
    {
        return [
            'test1' => 'test1',
            ...$this->test(),
            ...[
                'test3' => 'test3',
            ],
        ];
    }

    private function test(): array
    {
        return [
            'test2' => 'test2',
        ];
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())
        ->toBe('(): array{test1: string(test1), test2: string(test2), test3: string(test3)}');
});

it('transforms a non-literal array spread as additional properties', function () {
    $class = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo(array $payload): array
    {
        return [
            ...$payload,
            'note' => 'flat',
        ];
    }
}
EOD)->getClassDefinition('Foo');

    $transformer = app(TypeTransformer::class, [
        'context' => new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig),
    ]);

    expect($transformer->transform($class->methods['foo']->type->returnType)->toArray())
        ->toEqual([
            'type' => 'object',
            'properties' => [
                'note' => [
                    'type' => 'string',
                    'const' => 'flat',
                ],
            ],
            'required' => ['note'],
            'additionalProperties' => (object) [],
        ]);
});
