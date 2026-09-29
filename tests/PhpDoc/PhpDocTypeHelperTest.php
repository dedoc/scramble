<?php

use Composer\InstalledVersions;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Tests\Files\Status;

function getPhpTypeFromDoc_Copy(string $phpDoc)
{
    $docNode = PhpDoc::parse($phpDoc);
    $varNode = $docNode->getVarTagValues()[0];

    return PhpDocTypeHelper::toType($varNode->type);
}

it('parses php doc into type correctly', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ['/** @var Foo */', 'Foo'],
    ['/** @var Foo<Bar, Baz> */', 'Foo<Bar, Baz>'],
]);

it('parses nullable types', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ['/** @var ?string */', 'string|null'],
]);

it('resolves nullable enum types', function (string $phpDocType, string $expectedTypeString) {
    $docNode = PhpDoc::parse($phpDocType, FileNameResolver::createForFile(__FILE__));
    $varNode = $docNode->getVarTagValues()[0];

    expect(PhpDocTypeHelper::toType($varNode->type)->toString())
        ->toBe($expectedTypeString);
})->with([
    ['/** @var ?Status */', Status::class.'|null'],
]);

it('parses tuple', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ['/** @var array{float, float} */', 'list{float, float}'],
]);

it('parses class-string', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ['/** @var class-string<mixed> */', 'class-string<mixed>'],
]);

it('parses list', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ['/** @var list<float> */', 'array<float>'],
]);

it('parses integers', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ['/** @var int */', 'int'],
    ['/** @var integer */', 'int'],
    ['/** @var positive-int */', 'int<1, max>'],
    ['/** @var negative-int */', 'int<min, -1>'],
    ['/** @var non-positive-int */', 'int<min, 0>'],
    ['/** @var non-negative-int */', 'int<0, max>'],
    ['/** @var non-zero-int */', 'int'],
    ['/** @var int<10, 11> */', 'int<10, 11>'],
    ['/** @var int<10, max> */', 'int<10, max>'],
    ['/** @var int<min, 10> */', 'int<min, 10>'],
    ['/** @var int<max, 10> */', 'int'],
    ['/** @var int<10, min> */', 'int'],
    ['/** @var int<0x10, 0xFF> */', 'int<16, 255>'],
    ['/** @var int<-0b11, 0o17> */', 'int<-3, 15>'],
    ['/** @var 42 */', 'int(42)'],
    ['/** @var -42 */', 'int(-42)'],
    ['/** @var 0x1F */', 'int(31)'],
    ['/** @var -0x1F */', 'int(-31)'],
    ['/** @var 0x7FFFFFFFFFFFFFFF */', 'int(9223372036854775807)'],
    ['/** @var 0x8000000000000000 */', 'int'],
    ['/** @var 0xFFFFFFFFFFFFFFFF */', 'int'],
    ['/** @var 0x000000008000000000000000 */', 'int'],
    ['/** @var -0x8000000000000000 */', 'int(-9223372036854775808)'],
    ['/** @var -0x8000000000000001 */', 'int'],
    ['/** @var 0b101 */', 'int(5)'],
    ['/** @var 0o17 */', 'int(15)'],
    ['/** @var 017 */', 'int(15)'],
    ['/** @var 0 */', 'int(0)'],
    ['/** @var 0x01|0x02 */', 'int(1)|int(2)'],
]);

it('parses integers with digit separators', function () {
    expect(getPhpTypeFromDoc_Copy('/** @var 1_000 */')->toString())->toBe('int(1000)');
})->skip(
    fn () => version_compare(InstalledVersions::getVersion('phpstan/phpdoc-parser') ?? '0', '1.21.0', '<'),
    'phpdoc-parser reads digit separators since 1.21.0',
);

it('parses strings', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ['/** @var string */', 'string'],
    ['/** @var non-empty-string */', 'string'],
    ['/** @var callable-string */', 'string'],
    ['/** @var numeric-string */', 'string'],
    ['/** @var non-falsy-string */', 'string'],
    ['/** @var truthy-string */', 'string'],
    ['/** @var literal-string */', 'string'],
    ['/** @var lowercase-string */', 'string'],
    ['/** @var uppercase-string */', 'string'],
    ['/** @var non-empty-lowercase-string */', 'string'],
    ['/** @var non-empty-uppercase-string */', 'string'],
    ['/** @var non-empty-literal-string */', 'string'],
]);

it('parses unions', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ["/** @var 'idle'|'charging'|'discharging'|null */", 'string(idle)|string(charging)|string(discharging)|null'],
]);

it('normalizes legacy Collection|T[] phpdoc idiom', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    [
        '/** @var \Illuminate\Support\Collection|\App\Models\Event[] */',
        '\Illuminate\Support\Collection<int, \App\Models\Event>',
    ],
    [
        '/** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Event[] */',
        '\Illuminate\Database\Eloquent\Collection<int, \App\Models\Event>',
    ],
    [
        '/** @var \Illuminate\Support\Collection|\App\Models\Event[]|null */',
        '\Illuminate\Support\Collection<int, \App\Models\Event>|null',
    ],
    [
        '/** @var \Illuminate\Support\Collection|array<\App\Models\Event> */',
        '\Illuminate\Support\Collection<int, \App\Models\Event>',
    ],
    // Already generic — leave alone
    [
        '/** @var \Illuminate\Support\Collection<int, \App\Models\Event>|\App\Models\Event[] */',
        '\Illuminate\Support\Collection<int, \App\Models\Event>|array<\App\Models\Event>',
    ],
    // Bare array without item type — leave alone
    [
        '/** @var \Illuminate\Support\Collection|array */',
        '\Illuminate\Support\Collection|array<mixed>',
    ],
    // Ambiguous: multiple collections — leave alone
    [
        '/** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|\App\Models\Event[] */',
        '\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array<\App\Models\Event>',
    ],
]);
