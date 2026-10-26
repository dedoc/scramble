<?php

use Dedoc\Scramble\Support\Type\UnknownType;

it(
    'infers enum case properties',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['Status_EnumPropertyFetchTest::Draft->name', 'string(Draft)'],
    ['Status_EnumPropertyFetchTest::Draft->value', 'string(draft)'],
    ['Status_EnumPropertyFetchTest::Published->value', 'string(published)'],
    ['Code_EnumPropertyFetchTest::Ok->name', 'string(Ok)'],
    ['Code_EnumPropertyFetchTest::Ok->value', 'int(200)'],
    ['Unit_EnumPropertyFetchTest::First->name', 'string(First)'],
]);

it(
    'infers enum properties when the case is unknown',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['(fn (Status_EnumPropertyFetchTest $status) => $status->name)($status)', 'string(Draft)|string(Published)'],
    ['(fn (Status_EnumPropertyFetchTest $status) => $status->value)($status)', 'string(draft)|string(published)'],
    ['(fn (Code_EnumPropertyFetchTest $code) => $code->name)($code)', 'string(Ok)|string(Missing)'],
    ['(fn (Code_EnumPropertyFetchTest $code) => $code->value)($code)', 'int(200)|int(404)'],
    ['(fn (Unit_EnumPropertyFetchTest $unit) => $unit->name)($unit)', 'string(First)|string(Second)'],
    ['(fn (Empty_EnumPropertyFetchTest $enum) => $enum->name)($enum)', 'never'],
    ['(fn (EmptyBacked_EnumPropertyFetchTest $enum) => $enum->name)($enum)', 'never'],
    ['(fn (EmptyBacked_EnumPropertyFetchTest $enum) => $enum->value)($enum)', 'never'],
]);

it(
    'does not infer nonexistent enum properties',
    fn ($statement) => expect(getStatementType($statement))->toBeInstanceOf(UnknownType::class),
)->with([
    'Unit_EnumPropertyFetchTest::First->value',
    'Status_EnumPropertyFetchTest::Draft->missing',
]);

enum Status_EnumPropertyFetchTest: string
{
    case Draft = 'draft';
    case Published = self::PUBLISHED_VALUE;

    private const PUBLISHED_VALUE = 'published';
}

enum Code_EnumPropertyFetchTest: int
{
    case Ok = 200;
    case Missing = 404;
}

enum Unit_EnumPropertyFetchTest
{
    case First;
    case Second;
}

enum Empty_EnumPropertyFetchTest {}

enum EmptyBacked_EnumPropertyFetchTest: string {}
