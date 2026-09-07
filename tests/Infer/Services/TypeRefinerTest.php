<?php

namespace Dedoc\Scramble\Tests\Infer\Services;

use Dedoc\Scramble\Infer\Services\TypeRefiner;
use Dedoc\Scramble\Support\Type\Type;
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
        return [
            TestUtils::parseType('array<array-key, mixed>'),
            TestUtils::parseType('list{string, string}'),
            TestUtils::parseType('list{string, string}'),
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
]);
