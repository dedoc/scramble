<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow;

it('serializes an oauth2 scheme with a device flow for the target version', function (OpenApiVersion $version, array $expected) {
    $scheme = SecurityScheme::oauth2()
        ->setDeprecated(false)
        ->setOauth2MetadataUrl('https://auth.example.com/.well-known/oauth-authorization-server')
        ->flow('clientCredentials', fn (OAuthFlow $flow) => $flow
            ->tokenUrl('https://auth.example.com/token')
            ->addScope('read', 'Read access'))
        ->flow('deviceAuthorization', fn (OAuthFlow $flow) => $flow
            ->deviceAuthorizationUrl('https://auth.example.com/device')
            ->tokenUrl('https://auth.example.com/token')
            ->addScope('read', 'Read access'));

    expect(serializeAsVersion($scheme, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V3_1, [
        'type' => 'oauth2',
        'flows' => [
            'clientCredentials' => [
                'tokenUrl' => 'https://auth.example.com/token',
                'scopes' => ['read' => 'Read access'],
            ],
        ],
    ]],
    [OpenApiVersion::V3_2, [
        'type' => 'oauth2',
        'deprecated' => false,
        'oauth2MetadataUrl' => 'https://auth.example.com/.well-known/oauth-authorization-server',
        'flows' => [
            'clientCredentials' => [
                'tokenUrl' => 'https://auth.example.com/token',
                'scopes' => ['read' => 'Read access'],
            ],
            'deviceAuthorization' => [
                'tokenUrl' => 'https://auth.example.com/token',
                'deviceAuthorizationUrl' => 'https://auth.example.com/device',
                'scopes' => ['read' => 'Read access'],
            ],
        ],
    ]],
]);
