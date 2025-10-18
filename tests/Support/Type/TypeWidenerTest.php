<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Tests\TestUtils;

test('types widening', function (string $type, string $expectedType) {
    $type = TestUtils::parseType($type);

    expect($type->widen()->toString())->toBe($expectedType);
})->with([
    ['true|false', 'boolean'],
    ['true|false|true', 'boolean'],
    ['int|42', 'int'],
    ['42|69', 'int(42)|int(69)'],
    ['string|"wow"', 'string'],
]);
