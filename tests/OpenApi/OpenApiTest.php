<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;

it('serializes an openapi object for the target version', function (OpenApiVersion $version, array $expected) {
    $openApi = (new OpenApi)
        ->setInfo(InfoObject::make('API')->setVersion('0.0.1'))
        ->setSelf('https://example.com/openapi.json');

    expect(serializeAsVersion($openApi, $version))->toBe($expected);
})->with([
    [
        OpenApiVersion::V31,
        [
            'openapi' => '3.1.2',
            'info' => ['title' => 'API', 'version' => '0.0.1'],
        ],
    ],
    [
        OpenApiVersion::V32,
        [
            'openapi' => '3.2.0',
            'info' => ['title' => 'API', 'version' => '0.0.1'],
            '$self' => 'https://example.com/openapi.json',
        ],
    ],
]);
