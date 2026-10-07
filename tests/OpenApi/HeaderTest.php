<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\MediaType;

it('serializes header content with a media type reference for the target version', function (OpenApiVersion $version, array $expected) {
    $reference = (new Components)->addMediaType('Metadata', (new MediaType)->setExample(['revision' => 1]));
    $header = (new Header)->addContent('application/json', $reference);

    expect(serializeAsVersion($header, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V31, [
        'content' => ['application/json' => ['example' => ['revision' => 1]]],
    ]],
    [OpenApiVersion::V32, [
        'content' => ['application/json' => ['$ref' => '#/components/mediaTypes/Metadata']],
    ]],
]);
