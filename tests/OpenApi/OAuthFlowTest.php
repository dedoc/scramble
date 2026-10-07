<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow;

it('serializes a device authorization url for the target version', function (OpenApiVersion $version, array $expected) {
    $flow = (new OAuthFlow)
        ->deviceAuthorizationUrl('https://auth.example.com/device')
        ->tokenUrl('https://auth.example.com/token');

    expect(serializeAsVersion($flow, $version))->toEqual($expected);
})->with([
    [OpenApiVersion::V3_1, [
        'tokenUrl' => 'https://auth.example.com/token',
        'scopes' => (object) [],
    ]],
    [OpenApiVersion::V3_2, [
        'tokenUrl' => 'https://auth.example.com/token',
        'deviceAuthorizationUrl' => 'https://auth.example.com/device',
        'scopes' => (object) [],
    ]],
]);
