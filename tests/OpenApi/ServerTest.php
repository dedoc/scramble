<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Server;

it('serializes a server for the target version', function (OpenApiVersion $version, array $expected) {
    $server = Server::make('https://api.example.com')
        ->setDescription('Production environment')
        ->setName('production');

    expect(serializeAsVersion($server, $version))->toBe($expected);
})->with([
    [
        OpenApiVersion::V31,
        [
            'url' => 'https://api.example.com',
            'description' => 'Production environment',
        ],
    ],
    [
        OpenApiVersion::V32,
        [
            'url' => 'https://api.example.com',
            'description' => 'Production environment',
            'name' => 'production',
        ],
    ],
]);

it('serializes an openapi object with a server for the target version', function (OpenApiVersion $version, array $expected) {
    $openApi = (new OpenApi)
        ->setInfo(InfoObject::make('API')->setVersion('0.0.1'))
        ->addServer(
            Server::make('https://api.example.com')
                ->setDescription('Production environment')
                ->setName('production')
        );

    expect(serializeAsVersion($openApi, $version))->toBe($expected);
})->with([
    [
        OpenApiVersion::V31,
        [
            'openapi' => '3.1.2',
            'info' => ['title' => 'API', 'version' => '0.0.1'],
            'servers' => [[
                'url' => 'https://api.example.com',
                'description' => 'Production environment',
            ]],
        ],
    ],
    [
        OpenApiVersion::V32,
        [
            'openapi' => '3.2.0',
            'info' => ['title' => 'API', 'version' => '0.0.1'],
            'servers' => [[
                'url' => 'https://api.example.com',
                'description' => 'Production environment',
                'name' => 'production',
            ]],
        ],
    ],
]);
