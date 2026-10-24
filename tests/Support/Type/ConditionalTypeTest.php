<?php

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type;

it('resolves ConditionalType for template types', function (Type\Type $subject, Type\Type $checkType, string $expectedTypeString) {
    $resolvedType = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new Type\ConditionalType(
        $subject,
        $checkType,
        new Type\Literal\LiteralStringType('true-branch'),
        new Type\Literal\LiteralStringType('false-branch'),
    ));

    expect($resolvedType->toString())->toBe($expectedTypeString);
})->with([
    'closure' => [
        new Type\FunctionType('{}', [], new Type\VoidType),
        new Type\FunctionType('{}', [], new Type\VoidType),
        'string(true-branch)',
    ],
    'string' => [
        new Type\Literal\LiteralStringType('user'),
        new Type\StringType,
        'string(true-branch)',
    ],
    'array' => [
        new Type\KeyedArrayType([
            new Type\ArrayItemType_('posts', new Type\FunctionType('{}', [], new Type\VoidType)),
        ]),
        new Type\KeyedArrayType,
        'string(true-branch)',
    ],
]);

it('resolves conditional types with definite and possible matches', function (Type\Type $subject, Type\Type $checkType, ?bool $matches) {
    foreach ([false, true] as $negated) {
        $resolvedType = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new Type\ConditionalType(
            $subject,
            $checkType,
            new Type\Literal\LiteralStringType('yes'),
            new Type\Literal\LiteralStringType('no'),
            negated: $negated,
        ));

        if ($matches === null) {
            expect(explode('|', $resolvedType->toString()))->toEqualCanonicalizing(['string(yes)', 'string(no)']);
        } else {
            expect($resolvedType->toString())->toBe($matches !== $negated ? 'string(yes)' : 'string(no)');
        }
    }
})->with([
    'broad string' => [new Type\StringType, new Type\Literal\LiteralStringType('yes'), null],
    'broad integer' => [new Type\IntegerType, new Type\Literal\LiteralIntegerType(1), null],
    'broad boolean' => [new Type\BooleanType, new Type\Literal\LiteralBooleanType(true), null],
    'same literal' => [new Type\Literal\LiteralStringType('yes'), new Type\Literal\LiteralStringType('yes'), true],
    'different strings' => [new Type\Literal\LiteralStringType('no'), new Type\Literal\LiteralStringType('yes'), false],
    'different integers' => [new Type\Literal\LiteralIntegerType(2), new Type\Literal\LiteralIntegerType(1), false],
    'different booleans' => [new Type\Literal\LiteralBooleanType(false), new Type\Literal\LiteralBooleanType(true), false],
    'disjoint types' => [new Type\IntegerType, new Type\StringType, false],
    'object subtype' => [new Type\ObjectType(\Exception::class), new Type\ObjectType(\RuntimeException::class), null],
    'mixed' => [new Type\MixedType, new Type\StringType, null],
    'partial union' => [new Type\Union([new Type\StringType, new Type\IntegerType]), new Type\StringType, null],
    'uncertain union' => [new Type\Union([new Type\StringType, new Type\IntegerType]), new Type\Literal\LiteralStringType('yes'), null],
    'matching union' => [new Type\Union([new Type\Literal\LiteralStringType('yes'), new Type\Literal\LiteralStringType('no')]), new Type\StringType, true],
    'disjoint union' => [new Type\Union([new Type\IntegerType, new Type\BooleanType]), new Type\StringType, false],
    'union check' => [new Type\StringType, new Type\Union([new Type\IntegerType, new Type\StringType]), true],
    'partial union check' => [new Type\StringType, new Type\Union([new Type\IntegerType, new Type\Literal\LiteralStringType('yes')]), null],
]);

it('resolves nested ConditionalType for template types', function () {
    $resolvedType = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new Type\ConditionalType(
        new Type\FunctionType('{}', [], new Type\VoidType),
        new Type\FunctionType('{}', [], new Type\VoidType),
        new Type\KeyedArrayType([
            new Type\ArrayItemType_(null, new Type\Literal\LiteralStringType('posts')),
        ], isList: true),
        new Type\ConditionalType(
            new Type\Literal\LiteralStringType('user'),
            new Type\StringType,
            new Type\KeyedArrayType([
                new Type\ArrayItemType_(0, new Type\Literal\LiteralStringType('user')),
                new Type\ArrayItemType_(1, new Type\Literal\LiteralStringType('comments')),
            ]),
            new Type\KeyedArrayType([
                new Type\ArrayItemType_('posts', new Type\Literal\LiteralStringType('posts')),
            ]),
        ),
    ));

    expect($resolvedType->toString())->toBe('list{string(posts)}');
});

it('does not resolve ConditionalType while subject template is unknown', function () {
    $resolvedType = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new Type\ConditionalType(
        new Type\TemplateType('TRelations'),
        new Type\StringType,
        new Type\Literal\LiteralStringType('yes'),
        new Type\Literal\LiteralStringType('no'),
    ));

    expect($resolvedType)->toBeInstanceOf(Type\ConditionalType::class);
});
