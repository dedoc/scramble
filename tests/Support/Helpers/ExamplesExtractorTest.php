<?php

use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Dedoc\Scramble\Support\PhpDoc;

it('extracts a single @example tag', function () {
    $docNode = PhpDoc::parse(<<<'DOC'
/**
 * @example foo
 */
DOC);

    expect(ExamplesExtractor::make($docNode)->extract())->toBe(['foo']);
});

it('extracts multiple @example tags', function () {
    $docNode = PhpDoc::parse(<<<'DOC'
/**
 * @example foo
 * @example bar
 * @example Multiword example
 */
DOC);

    expect(ExamplesExtractor::make($docNode)->extract())->toBe([
        'foo',
        'bar',
        'Multiword example',
    ]);
});

it('extracts typed @example values', function () {
    $docNode = PhpDoc::parse(<<<'DOC'
/**
 * @example 42
 * @example true
 * @example null
 * @example {"key":"value"}
 */
DOC);

    expect(ExamplesExtractor::make($docNode)->extract())->toBe([
        42,
        true,
        null,
        ['key' => 'value'],
    ]);
});

it('keeps @example values as strings when preferString is true', function () {
    $docNode = PhpDoc::parse(<<<'DOC'
/**
 * @example 42
 * @example 3.14
 */
DOC);

    expect(ExamplesExtractor::make($docNode)->extract(preferString: true))->toBe([
        '42',
        '3.14',
    ]);
});

it('returns an empty array when no @example tags are present', function () {
    $docNode = PhpDoc::parse(<<<'DOC'
/**
 * A description.
 */
DOC);

    expect(ExamplesExtractor::make($docNode)->extract())->toBe([]);
});
