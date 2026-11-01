<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Tests\TestUtils;

it('checks type acceptance', function (string $type, string $accept, bool $result) {
    $acceptingType = TestUtils::parseType($type);
    $givenType = TestUtils::parseType($accept);

    expect($acceptingType->accepts($givenType))
        ->toBe($result, "$type is ".($result ? '' : 'not ')."expected to accept {$accept}.");
})->with([
    [
        'type' => 'int',
        'accept' => 'non-negative-int',
        'result' => true,
    ],
    [
        'type' => 'array{weeks: int, this_week: bool}',
        'accept' => 'array{weeks: int<0, max>, this_week: bool}',
        'result' => true,
    ],
    [
        'type' => 'array{weeks: int, this_week: bool}',
        'accept' => 'array{this_week: bool, weeks: int}',
        'result' => true,
    ],
    [
        'type' => 'array{weeks: int}',
        'accept' => 'array{weeks: int, this_week: bool}',
        'result' => true,
    ],
    [
        'type' => 'array{weeks: int, this_week?: bool}',
        'accept' => 'array{weeks: int}',
        'result' => true,
    ],
    [
        'type' => 'array{weeks: int, this_week?: bool}',
        'accept' => 'array{weeks: int, this_week: bool}',
        'result' => true,
    ],
    [
        'type' => 'non-negative-int',
        'accept' => 'int',
        'result' => false,
    ],
    [
        'type' => 'array{weeks: int<0, max>, this_week: bool}',
        'accept' => 'array{weeks: int, this_week: bool}',
        'result' => false,
    ],
    [
        'type' => 'array{weeks: int, this_week: bool}',
        'accept' => 'array{weeks: int}',
        'result' => false,
    ],
    [
        'type' => 'array{weeks: int, this_week: bool}',
        'accept' => 'array{weeks: int, this_week?: bool}',
        'result' => false,
    ],
    [
        'type' => 'array{weeks: int}',
        'accept' => 'array{weeks: string}',
        'result' => false,
    ],
    [
        'type' => 'array{int, bool}',
        'accept' => 'array{bool, int}',
        'result' => false,
    ],
    'different keys with matching value types' => [
        'type' => 'array{x: int}',
        'accept' => 'array{y: int}',
        'result' => false,
    ],
    'optional accepts optional' => [
        'type' => 'array{x?: int}',
        'accept' => 'array{x?: non-negative-int}',
        'result' => true,
    ],
    'optional field rejects incompatible required value' => [
        'type' => 'array{x?: int}',
        'accept' => 'array{x: string}',
        'result' => false,
    ],
    'optional field rejects incompatible optional value' => [
        'type' => 'array{x?: int}',
        'accept' => 'array{x?: string}',
        'result' => false,
    ],
    'all optional fields accept empty shape' => [
        'type' => 'array{x?: int}',
        'accept' => 'array{}',
        'result' => true,
    ],
    'nullable field is still required' => [
        'type' => 'array{x: int|null}',
        'accept' => 'array{}',
        'result' => false,
    ],
    'nullable field accepts null' => [
        'type' => 'array{x: int|null}',
        'accept' => 'array{x: null}',
        'result' => true,
    ],
    'nested shape accepts extra keys' => [
        'type' => 'array{child: array{x: int}}',
        'accept' => 'array{child: array{x: int, y: bool}}',
        'result' => true,
    ],
    'extra keys do not replace required keys' => [
        'type' => 'array{x: int}',
        'accept' => 'array{y: int, z: bool}',
        'result' => false,
    ],
    'empty shape accepts itself' => [
        'type' => 'array{}',
        'accept' => 'array{}',
        'result' => true,
    ],
    'empty shape accepts extra named keys' => [
        'type' => 'array{}',
        'accept' => 'array{x: int}',
        'result' => true,
    ],
    'empty shape accepts extra numeric keys' => [
        'type' => 'array{}',
        'accept' => 'array{int, bool}',
        'result' => true,
    ],
    'tuple accepts explicit numeric keys' => [
        'type' => 'array{int, bool}',
        'accept' => 'array{0: int, 1: bool}',
        'result' => true,
    ],
    'explicit numeric keys accept tuple' => [
        'type' => 'array{0: int, 1: bool}',
        'accept' => 'array{int, bool}',
        'result' => true,
    ],
    'tuple rejects missing element' => [
        'type' => 'array{int, bool}',
        'accept' => 'array{int}',
        'result' => false,
    ],
    'tuple accepts extra elements' => [
        'type' => 'array{int, bool}',
        'accept' => 'array{int, bool, string}',
        'result' => true,
    ],
    'generic array accepts compatible shape' => [
        'type' => 'array<string, int>',
        'accept' => 'array{x: int}',
        'result' => true,
    ],
]);
