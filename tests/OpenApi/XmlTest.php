<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Xml;

it('serializes an explicit xml node type for the target version', function (OpenApiVersion $version, array $expected) {
    $xml = (new Xml(name: 'books', attribute: false, wrapped: true))
        ->setNodeType('element');

    expect(serializeAsVersion($xml, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V31, ['name' => 'books', 'attribute' => false, 'wrapped' => true]],
    [OpenApiVersion::V32, ['name' => 'books', 'nodeType' => 'element']],
]);

it('preserves legacy xml fields without an explicit node type', function (OpenApiVersion $version, array $expected) {
    $xml = new Xml(name: 'books', attribute: false, wrapped: true);

    expect(serializeAsVersion($xml, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V31, ['name' => 'books', 'attribute' => false, 'wrapped' => true]],
    [OpenApiVersion::V32, ['name' => 'books', 'attribute' => false, 'wrapped' => true]],
]);
