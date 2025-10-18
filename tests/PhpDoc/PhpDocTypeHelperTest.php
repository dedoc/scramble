<?php

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;

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

it('parses tuple', function (string $phpDocType, string $expectedTypeString) {
    expect(
        getPhpTypeFromDoc_Copy($phpDocType)->toString()
    )->toBe($expectedTypeString);
})->with([
    ['/** @var array{float, float} */', 'list{float, float}'],
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
]);
