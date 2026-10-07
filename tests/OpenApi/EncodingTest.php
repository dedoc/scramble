<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Encoding;
use Dedoc\Scramble\Support\Generator\MediaType;

it('serializes nested encodings for the target version', function (OpenApiVersion $version, array $expected) {
    $encoding = (new Encoding(contentType: 'multipart/form-data'))
        ->setEncoding([
            'attachments' => (new Encoding(contentType: 'multipart/mixed'))
                ->setPrefixEncoding([
                    new Encoding(contentType: 'application/json'),
                    new Encoding(contentType: 'image/png'),
                ])
                ->setItemEncoding(new Encoding(contentType: 'application/octet-stream')),
        ]);

    expect(serializeAsVersion($encoding, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V3_1, ['contentType' => 'multipart/form-data']],
    [OpenApiVersion::V3_2, [
        'contentType' => 'multipart/form-data',
        'encoding' => [
            'attachments' => [
                'contentType' => 'multipart/mixed',
                'prefixEncoding' => [
                    ['contentType' => 'application/json'],
                    ['contentType' => 'image/png'],
                ],
                'itemEncoding' => ['contentType' => 'application/octet-stream'],
            ],
        ],
    ]],
]);

it('serializes empty positional encodings as objects', function (OpenApiVersion $version, string $expected) {
    $mediaType = (new MediaType)
        ->setPrefixEncoding([new Encoding])
        ->setItemEncoding(new Encoding);

    expect(json_encode(serializeAsVersion($mediaType, $version), JSON_THROW_ON_ERROR))->toBe($expected);
})->with([
    [OpenApiVersion::V3_1, '{}'],
    [OpenApiVersion::V3_2, '{"prefixEncoding":[{}],"itemEncoding":{}}'],
]);
