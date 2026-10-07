<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Encoding;

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
    [OpenApiVersion::V31, ['contentType' => 'multipart/form-data']],
    [OpenApiVersion::V32, [
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
