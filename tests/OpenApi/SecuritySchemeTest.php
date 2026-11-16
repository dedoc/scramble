<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlows;

it('restores exported security schemes for php artisan config:cache', function (SecurityScheme $scheme) {
    $scheme->as('customAuth')
        ->setDescription('Authenticate API requests')
        ->setDeprecated(false)
        ->setOauth2MetadataUrl('https://auth.example.com/metadata')
        ->default()
        ->mergeExtensionProperties(['audiences' => ['api']]);

    // config:cache exports configuration with var_export() and requires the generated PHP.
    // Test that restoration step directly to avoid a full application reboot and temporary config files.
    //
    // @see https://github.com/laravel/framework/blob/da870df31b401d07088ab20dd3429ad245a49140/src/Illuminate/Foundation/Console/ConfigCacheCommand.php#L65
    $restored = eval('return '.var_export($scheme, true).';');

    expect($restored)->toEqual($scheme)
        ->and($restored->serializeAs32())->toBe($scheme->serializeAs32());
})->with([
    'http' => [SecurityScheme::http('bearer', 'JWT')],
    'apiKey' => [SecurityScheme::apiKey('header', 'X-API-Key')],
    'openIdConnect' => [SecurityScheme::openIdConnect('https://auth.example.com/.well-known/openid-configuration')],
    'mutualTLS' => [SecurityScheme::mutualTLS()],
    'oauth2' => [
        SecurityScheme::oauth2()
            ->flows(fn (OAuthFlows $flows) => $flows
                ->mergeExtensionProperties(['audiences' => ['api']]))
            ->flow('deviceAuthorization', fn (OAuthFlow $flow) => $flow
                ->authorizationUrl('https://auth.example.com/authorize')
                ->tokenUrl('https://auth.example.com/token')
                ->refreshUrl('https://auth.example.com/refresh')
                ->deviceAuthorizationUrl('https://auth.example.com/device')
                ->addScope('read', 'Read access')
                ->mergeExtensionProperties(['audiences' => ['api']])),
    ],
]);

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
