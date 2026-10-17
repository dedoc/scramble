<?php

use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\LiteralTypeEvaluator;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;

it('evaluates literal values', function (Type $type, mixed $expected) {
    expect((new LiteralTypeEvaluator)->evaluate($type))->toBe($expected);
})->with([
    'string' => [new LiteralStringType('required'), 'required'],
    'integer' => [new LiteralIntegerType(0), 0],
    'float' => [new LiteralFloatType(1.5), 1.5],
    'boolean' => [new LiteralBooleanType(false), false],
    'null' => [new NullType, null],
    'empty array' => [new KeyedArrayType, []],
    'union' => [new Union([new StringType, new LiteralStringType('email'), new LiteralStringType('url')]), 'email'],
    'nullable union' => [new Union([new StringType, new NullType, new LiteralStringType('email')]), null],
]);

it('evaluates nested arrays and unions in array values', function () {
    $type = new Union([
        new StringType,
        new KeyedArrayType([
            new ArrayItemType_('name', new KeyedArrayType([
                new ArrayItemType_(null, new LiteralStringType('required')),
                new ArrayItemType_(null, new Union([new StringType, new LiteralStringType('string')])),
            ])),
            new ArrayItemType_('email', new Union([
                new StringType,
                new KeyedArrayType([
                    new ArrayItemType_(null, new LiteralStringType('email')),
                ]),
            ])),
        ]),
    ]);

    expect((new LiteralTypeEvaluator)->evaluate($type))->toBe([
        'name' => ['required', 'string'],
        'email' => ['email'],
    ]);
});

it('appends unkeyed items using PHP array indexing', function () {
    $type = new KeyedArrayType([
        new ArrayItemType_('name', new LiteralStringType('string')),
        new ArrayItemType_(null, new LiteralStringType('required')),
        new ArrayItemType_(5, new LiteralStringType('email')),
        new ArrayItemType_(null, new NullType),
    ]);

    expect((new LiteralTypeEvaluator)->evaluate($type))->toBe([
        'name' => 'string',
        0 => 'required',
        5 => 'email',
        6 => null,
    ]);
});

it('rejects types without an evaluable literal', function () {
    (new LiteralTypeEvaluator)->evaluate(new Union([new StringType]));
})->throws(RuntimeException::class, 'Cannot pick evaluable type');
