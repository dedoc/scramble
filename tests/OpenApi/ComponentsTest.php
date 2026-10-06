<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Reference;

it('serializes reusable media types for the target version', function (OpenApiVersion $version, array $expected) {
    $components = (new Components)->setMediaTypes([
        'Payload' => (new MediaType)->setExample(['id' => 1]),
        'External' => Reference::fromUri('https://example.com/openapi.json#/components/mediaTypes/Payload'),
    ]);

    expect(serializeAsVersion($components, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V31, []],
    [
        OpenApiVersion::V32,
        [
            'mediaTypes' => [
                'Payload' => ['example' => ['id' => 1]],
                'External' => ['$ref' => 'https://example.com/openapi.json#/components/mediaTypes/Payload'],
            ],
        ],
    ],
]);

it('registers and resolves reusable media type references', function () {
    $components = new Components;
    $mediaType = new MediaType;
    $reference = $components->addMediaType('Payload', $mediaType);

    expect($reference->getUri())->toBe('#/components/mediaTypes/Payload')
        ->and($reference->resolve())->toBe($mediaType);

    $alias = $components->addMediaType('Alias', $reference);

    expect($alias->resolve())->toBe($reference)
        ->and($components->serializeAs32()['mediaTypes']['Alias'])
        ->toBe(['$ref' => '#/components/mediaTypes/Payload']);
});
