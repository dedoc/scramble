<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\PhpDoc;

use function Spatie\Snapshots\assertMatchesSnapshot;

// @todo move all tests into PhpDoc/PhpDocTypeHelperTest

function getTypeFromDoc(string $phpDoc)
{
    $docNode = PhpDoc::parse($phpDoc);
    $varNode = $docNode->getVarTagValues()[0];

    return app()->make(TypeTransformer::class, [
        'context' => new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig),
    ])->transform(PhpDocTypeHelper::toType($varNode->type));
}

function getPhpTypeFromDoc(string $phpDoc)
{
    $docNode = PhpDoc::parse($phpDoc);
    $varNode = $docNode->getVarTagValues()[0];

    return PhpDocTypeHelper::toType($varNode->type);
}

it('handles simple types', function ($phpDoc) {
    $result = getTypeFromDoc($phpDoc);

    assertMatchesSnapshot($result ? $result->toArray() : null);
})->with([
    '/** @var string */',
    '/** @var int */',
    '/** @var integer */',
    '/** @var float */',
    '/** @var bool */',
    '/** @var boolean */',
    '/** @var true */',
    '/** @var false */',
    '/** @var float */',
    '/** @var double */',
    '/** @var scalar */',
    '/** @var array */',
    '/** @var null */',
    '/** @var object */',
]);

it('handles literal types', function ($phpDoc, $expectedType) {
    $result = getPhpTypeFromDoc($phpDoc);

    expect($result->toString())->toBe($expectedType);
})->with([
    ["/** @var 'foo' */", 'string(foo)'],
    ['/** @var true */', 'boolean(true)'],
    ['/** @var false */', 'boolean(false)'],
    ["/** @var array{'foo': string} */", 'array{foo: string}'],
]);

/**
 * @see https://phpstan.org/writing-php-code/phpdoc-types#general-arrays
 */
it('handles general arrays', function ($phpDoc) {
    $result = getTypeFromDoc($phpDoc);

    assertMatchesSnapshot($result ? $result->toArray() : null);
})->with([
    '/** @var string[] */',
    '/** @var array<string> */',
    '/** @var array<int, string> */',
    '/** @var array<string, string> */',
]);

it('handles shape arrays', function ($phpDoc) {
    $result = getTypeFromDoc($phpDoc);

    assertMatchesSnapshot($result ? $result->toArray() : null);
})->with([
    '/** @var array{string} */', // list with one item
    '/** @var array{int, string} */', // list
    '/** @var array{0: string, 1: string} */', // list
    '/** @var array{wow: string} */', // keyed
    '/** @var array{test: string, wow?: string} */', // keyed, test var here is added so snapshot name generates correctly
    '/** @var array{string, string} */', // list
]);

it('handles intersection type', function () {
    $phpDoc = '/** @var array{test: string, wow?: string} & array{nice: bool} & array{kek: bool} */';
    $result = getTypeFromDoc($phpDoc);

    assertMatchesSnapshot($result ? $result->toArray() : null);
});

it('handles union type', function () {
    $phpDoc = '/** @var array{test: string, wow?: string} | array{nice: bool} | array{kek: bool} */';
    $result = getTypeFromDoc($phpDoc);

    assertMatchesSnapshot($result ? $result->toArray() : null);
});

it('handles unions of string literals', function ($phpDoc) {
    $result = getTypeFromDoc($phpDoc);

    assertMatchesSnapshot($result ? $result->toArray() : null);
})->with([
    "/** @var 'foo'|'bar' */",
    "/** @var 'foo'|'bar'|string */",
]);
