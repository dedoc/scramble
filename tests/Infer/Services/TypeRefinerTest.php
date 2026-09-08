<?php

namespace Dedoc\Scramble\Tests\Infer\Services;

use Dedoc\Scramble\Infer\Services\TypeRefiner;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Tests\TestUtils;

it('refines inferred type against declared type', function (Type $inferred, Type $declared, Type $expected) {
    $result = (new TypeRefiner)->refine($declared, $inferred);

    expect($result->toString())->toBe($expected->toString())
        ->and($result->getAttribute('format'))->toBe($expected->getAttribute('format'));
})->with([
    'Carbon with format:date vs Carbon' => function () {
        $inferred = TestUtils::parseType('Carbon');
        $inferred->setAttribute('format', 'date');

        $expected = TestUtils::parseType('Carbon');
        $expected->setAttribute('format', 'date');

        return [$inferred, TestUtils::parseType('Carbon'), $expected];
    },
    'array<array-key, mixed> vs list{string, string}' => function () {
        $declared = new KeyedArrayType([
            new ArrayItemType_(null, new StringType),
            new ArrayItemType_(null, new StringType),
        ], isList: true);

        return [
            new ArrayType(new MixedType, new Union([new IntegerType, new StringType])),
            $declared,
            $declared,
        ];
    },
    'SponsorshipsCollection<int, Sponsorship> vs SponsorshipsCollection' => function () {
        return [
            TestUtils::parseType('SponsorshipsCollection<int, Sponsorship>'),
            TestUtils::parseType('SponsorshipsCollection'),
            TestUtils::parseType('SponsorshipsCollection<int, Sponsorship>'),
        ];
    },
    'string vs string|int' => function () {
        return [
            TestUtils::parseType('string'),
            TestUtils::parseType('string|int'),
            TestUtils::parseType('string|int'),
        ];
    },
    'array shape enriches a generic declared array' => function () {
        $inferred = new KeyedArrayType([
            new ArrayItemType_(null, new StringType),
            new ArrayItemType_(null, new StringType),
        ], isList: true);

        return [
            $inferred,
            new ArrayType(new MixedType, new Union([new IntegerType, new StringType])),
            $inferred,
        ];
    },
    'nested array shape information' => function () {
        $inferred = new KeyedArrayType([
            new ArrayItemType_('data', new KeyedArrayType([
                new ArrayItemType_(null, new StringType),
                new ArrayItemType_(null, new StringType),
            ], isList: true)),
        ], isList: false);

        return [
            $inferred,
            new KeyedArrayType([
                new ArrayItemType_('data', new ArrayType(
                    new MixedType,
                    new Union([new IntegerType, new StringType]),
                )),
            ], isList: false),
            $inferred,
        ];
    },
]);

it('refines matching union members and retains every declared member', function () {
    $inferredCarbon = new ObjectType('Carbon');
    $inferredCarbon->setAttribute('format', 'date');

    $declared = new Union([new ObjectType('Carbon'), TestUtils::parseType('string')]);
    $inferred = new Union([TestUtils::parseType('int'), $inferredCarbon]);

    $result = (new TypeRefiner)->refine($declared, $inferred);
    $resultCarbon = (new TypeWalker)->first(
        $result,
        fn (Type $type) => $type instanceof ObjectType && $type->name === 'Carbon',
    );

    expect($result->toString())->toBe('Carbon|string')
        ->and($resultCarbon?->getAttribute('format'))->toBe('date');
});

it('only fills unknown generic arguments', function () {
    $declared = new Generic('Collection', [new UnknownType, TestUtils::parseType('string')]);
    $inferred = new Generic('Collection', [new IntegerType, new IntegerType]);

    $result = (new TypeRefiner)->refine($declared, $inferred);

    expect($result->toString())->toBe('Collection<int, string>');
});

it('does not mutate or return either input type', function () {
    $declared = new Generic('Collection', [new UnknownType]);
    $declared->setAttribute('source', 'declaration');
    $inferred = new Generic('Collection', [new ObjectType('Carbon')]);
    $inferred->templateTypes[0]->setAttribute('format', 'date');

    $result = (new TypeRefiner)->refine($declared, $inferred);
    $resultCarbon = (new TypeWalker)->first(
        $result,
        fn (Type $type) => $type instanceof ObjectType && $type->name === 'Carbon',
    );
    $resultCarbon?->setAttribute('format', 'date-time');

    expect($result)->not->toBe($declared)
        ->and($result)->not->toBe($inferred)
        ->and($resultCarbon)->not->toBeNull()
        ->and($declared->templateTypes[0])->toBeInstanceOf(UnknownType::class)
        ->and($inferred->templateTypes[0]->getAttribute('format'))->toBe('date')
        ->and($declared->getAttribute('source'))->toBe('declaration');
});
