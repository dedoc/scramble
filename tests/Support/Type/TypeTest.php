<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\ConditionalType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\NeverType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Tests\TestUtils;

it('checks type acceptance', function (string $type, string|Type $accept, bool $result) {
    $acceptingType = TestUtils::parseType($type);
    $givenType = is_string($accept) ? TestUtils::parseType($accept) : $accept;

    expect($acceptingType->accepts($givenType))
        ->toBe($result, "$type is ".($result ? '' : 'not ')."expected to accept {$givenType->toString()}.");
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
    'empty shape accepts extra named keys' => [
        'type' => 'array{}',
        'accept' => 'array{x: int}',
        'result' => true,
    ],
    'tuple accepts explicit numeric keys' => [
        'type' => 'array{int, bool}',
        'accept' => 'array{0: int, 1: bool}',
        'result' => true,
    ],
    'generic array accepts compatible shape' => [
        'type' => 'array<string, int>',
        'accept' => 'array{x: int}',
        'result' => true,
    ],
    [
        'type' => 'array{status: string}|null',
        'accept' => "array{status: 'complete'|'missing'}",
        'result' => true,
    ],
    [
        'type' => 'non-negative-int',
        'accept' => '0|1',
        'result' => true,
    ],
    [
        'type' => 'non-negative-int',
        'accept' => '0|-1',
        'result' => false,
    ],
    [
        'type' => 'array{x: int}',
        'accept' => 'array{x: 1}|array{x: 2}',
        'result' => true,
    ],
    [
        'type' => 'array{x: int}',
        'accept' => 'array{x: 1}|array{x: string}',
        'result' => false,
    ],
    [
        'type' => 'Exception',
        'accept' => 'Exception|RuntimeException',
        'result' => true,
    ],
    [
        'type' => 'Exception',
        'accept' => 'Exception|stdClass',
        'result' => false,
    ],
    [
        'type' => 'string|int',
        'accept' => new ConditionalType(
            new TemplateType('TFlag'),
            new LiteralBooleanType(true),
            TestUtils::parseType('string'),
            TestUtils::parseType('int'),
        ),
        'result' => true,
    ],
    [
        'type' => 'string|int',
        'accept' => new ConditionalType(
            new TemplateType('TFlag'),
            new LiteralBooleanType(true),
            TestUtils::parseType('string'),
            TestUtils::parseType('bool'),
        ),
        'result' => false,
    ],
    [
        'type' => 'string',
        'accept' => new ConditionalType(
            new TemplateType('TFlag'),
            new LiteralBooleanType(true),
            new NeverType,
            TestUtils::parseType('string'),
        ),
        'result' => true,
    ],
    [
        'type' => 'string',
        'accept' => new NeverType,
        'result' => false,
    ],
    [
        'type' => 'string',
        'accept' => new ConditionalType(
            new TemplateType('TFlag'),
            new LiteralBooleanType(true),
            new NeverType,
            TestUtils::parseType('int'),
        ),
        'result' => false,
    ],
    [
        'type' => 'mixed',
        'accept' => new UnknownType,
        'result' => false,
    ],
    [
        'type' => 'mixed',
        'accept' => new Union([TestUtils::parseType('string'), new UnknownType]),
        'result' => true,
    ],
]);
