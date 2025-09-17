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
