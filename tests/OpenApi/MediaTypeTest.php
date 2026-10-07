<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Encoding;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;

it('serializes a media type with positional encoding for the target version', function (OpenApiVersion $version, array $expected) {
    $mediaType = (new MediaType)
        ->setItemSchema(Schema::fromType(new StringType))
        ->setExample(['metadata', 'image'])
        ->setPrefixEncoding([
            new Encoding(contentType: 'text/plain'),
            new Encoding(contentType: 'image/png'),
        ])
        ->setItemEncoding(new Encoding(contentType: 'application/octet-stream'));

    expect(serializeAsVersion($mediaType, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V3_1, ['example' => ['metadata', 'image']]],
    [OpenApiVersion::V3_2, [
        'itemSchema' => ['type' => 'string'],
        'example' => ['metadata', 'image'],
        'prefixEncoding' => [
            ['contentType' => 'text/plain'],
            ['contentType' => 'image/png'],
        ],
        'itemEncoding' => ['contentType' => 'application/octet-stream'],
    ]],
]);
