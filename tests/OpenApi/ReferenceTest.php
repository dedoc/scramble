<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Reference;

it('serializes a media type reference for the target version', function (OpenApiVersion $version, array $expected) {
    $components = (new Components)->setMediaTypes([
        'Payload' => (new MediaType)->setExample(['id' => 1]),
    ]);
    $reference = new Reference('mediaTypes', 'Payload', $components);

    expect(serializeAsVersion($reference, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V3_1, ['example' => ['id' => 1]]],
    [OpenApiVersion::V3_2, ['$ref' => '#/components/mediaTypes/Payload']],
]);

it('inlines an empty media type as an object in 3.1', function () {
    $components = (new Components)->setMediaTypes(['Payload' => new MediaType]);
    $reference = new Reference('mediaTypes', 'Payload', $components);

    expect(json_encode($reference->serializeAs31(), JSON_THROW_ON_ERROR))->toBe('{}');
});
