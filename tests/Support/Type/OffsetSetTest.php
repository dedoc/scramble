<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Type;

it('handles array fetch', function () {
    expect(['a' => 1]['a'])->toHaveType('int(1)');
});

it('handles array set type', function () {
    $a = [];
    $a['foo'] = 42;

    expect($a)->toHaveType('array{foo: int(42)}');
});

it('handles array push type', function () {
    $a = [];
    $a[] = 42;
    $a[] = 1;

    expect($a)->toHaveType('list{int(42), int(1)}');
});

it('handles array modify type', function () {
    $a = ['foo' => 23];

    $a['foo'] = 42;

    expect($a)->toHaveType('array{foo: int(42)}');
});

it('handles array deep set type', function () {
    $a = [];
    $a['foo']['bar'] = 42;

    expect($a)->toHaveType('array{foo: array{bar: int(42)}}');
});

it('handles array deep modify type', function () {
    $a = ['foo' => []];
    $a['foo']['bar'] = 42;

    expect($a)->toHaveType('array{foo: array{bar: int(42)}}');
});

it('handles array deep push type', function () {
    $a = ['foo' => []];
    $a['foo']['bar'][] = 42;
    $a['foo']['bar'][] = 1;

    expect($a)->toHaveType('array{foo: array{bar: list{int(42), int(1)}}}');
});

it('allows setting keys on template type', function () {
    $a = function ($b) {
        $b['wow'] = 42;

        return $b;
    };

    $wow = ['foo' => 'bar'];
    $wow2 = $a($wow);

    expect($wow2)->toHaveType('array{foo: string(bar), wow: int(42)}');
});

it('allows setting keys on template type with deep methods logic', function () {
    $foo = new Foo_ExpressionsTest;

    $result = $foo->setC(['foo' => 'bar']);

    expect($result)->toHaveType('array{foo: string(bar), a: int(1), b: int(2), c: int(3)}');
})->skip('figure out test ns');
class Foo_ExpressionsTest
{
    public function get42()
    {
        return 42;
    }

    public function setA($data)
    {
        $data['a'] = 1;

        return $data;
    }

    public function setB($data)
    {
        $data = $this->setA($data);
        $data['b'] = 2;

        return $data;
    }

    public function setC($data)
    {
        $data = $this->setB($data);
        $data['c'] = 3;

        return $data;
    }
}

it('allows calling methods on retrieved types', function () {
    $arr = [];
    $foo = new Foo_ExpressionsTest;

    $arr['foo'] = $foo;

    $r = $arr['foo']->get42();

    expect($r)->toHaveType('int(42)');
})->skip('figure out test ns');

it('allows calling methods on deep retrieved types', function () {
    $arr = [
        'foo' => ['bar' => new Foo_ExpressionsTest],
    ];

    $r = $arr['foo']['bar']->get42();

    expect($r)->toHaveType('int(42)');
})->skip('figure out test ns');

it('preserves array key description when setting the offset from offset get', function () {
    $arr = [
        /** Foo description. */
        'foo' => 42,
    ];

    $newArr = [];
    $newArr['bar'] = $arr['foo'];

    expect($newArr)->toHaveType(function (Type $t) {
        $openApiTransformer = app(TypeTransformer::class, [
            'context' => new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig),
        ]);

        expect($openApiTransformer->transform($t)->toArray())->toBe([
            'type' => 'object',
            'properties' => [
                'bar' => [
                    'type' => 'integer',
                    'description' => 'Foo description.',
                    'enum' => [42],
                ],
            ],
            'required' => ['bar'],
        ]);

        return true;
    });
});

it('preserves array key description when setting the key from offset get', function () {
    $arr = [
        'bar' => [
            /** Foo description. */
            'foo' => 42,
        ],
    ];

    $newArr = [
        'bar' => $arr['bar']['foo'],
    ];

    expect($newArr)->toHaveType(function (Type $t) {
        $openApiTransformer = app(TypeTransformer::class, [
            'context' => new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig),
        ]);

        expect($openApiTransformer->transform($t)->toArray())->toBe([
            'type' => 'object',
            'properties' => [
                'bar' => [
                    'type' => 'integer',
                    'description' => 'Foo description.',
                    'enum' => [42],
                ],
            ],
            'required' => ['bar'],
        ]);

        return true;
    });
});
