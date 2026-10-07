<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Example;

it('serializes data and serialized examples for the target version', function (OpenApiVersion $version, array $expected) {
    $example = (new Example(summary: 'Repeated query parameter'))
        ->setDataValue(['hello world', 'goodbye'])
        ->setSerializedValue('tags=hello%20world&tags=goodbye');

    expect(serializeAsVersion($example, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V31, ['summary' => 'Repeated query parameter']],
    [OpenApiVersion::V32, [
        'summary' => 'Repeated query parameter',
        'dataValue' => ['hello world', 'goodbye'],
        'serializedValue' => 'tags=hello%20world&tags=goodbye',
    ]],
]);

it('preserves explicit null data and empty serialized examples for the target version', function (OpenApiVersion $version, array $expected) {
    $example = (new Example)->setDataValue(null)->setSerializedValue('');

    expect(serializeAsVersion($example, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V31, []],
    [OpenApiVersion::V32, ['dataValue' => null, 'serializedValue' => '']],
]);
