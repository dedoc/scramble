<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Server;

it('serializes additional operations for the target version', function (OpenApiVersion $version, array $expected) {
    $path = Path::make('files/{id}')
        ->addOperation(Operation::make('get')->summary('Read file'))
        ->addAdditionalOperation(
            'COPY',
            Operation::make('COPY')
                ->summary('Copy file')
                ->servers([Server::make('https://api.example.com')->setName('production')])
        );

    expect(serializeAsVersion($path, $version))->toBe($expected);
})->with([
    [
        OpenApiVersion::V3_1,
        ['get' => ['summary' => 'Read file']],
    ],
    [
        OpenApiVersion::V3_2,
        [
            'get' => ['summary' => 'Read file'],
            'additionalOperations' => [
                'COPY' => [
                    'summary' => 'Copy file',
                    'servers' => [[
                        'url' => 'https://api.example.com',
                        'name' => 'production',
                    ]],
                ],
            ],
        ],
    ],
]);
