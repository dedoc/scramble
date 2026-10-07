<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;

it('serializes an openapi object for the target version', function (OpenApiVersion $version, array $expected) {
    $openApi = (new OpenApi)
        ->setInfo(InfoObject::make('API')->setVersion('0.0.1'))
        ->setSelf('https://example.com/openapi.json');

    expect(serializeAsVersion($openApi, $version))->toBe($expected);
})->with([
    [
        OpenApiVersion::V3_1,
        [
            'openapi' => '3.1.2',
            'info' => ['title' => 'API', 'version' => '0.0.1'],
        ],
    ],
    [
        OpenApiVersion::V3_2,
        [
            'openapi' => '3.2.1',
            'info' => ['title' => 'API', 'version' => '0.0.1'],
            '$self' => 'https://example.com/openapi.json',
        ],
    ],
]);

it('serializes empty path items while merging duplicate paths', function (OpenApiVersion $version, string $expected) {
    $openApi = (new OpenApi)
        ->setInfo(InfoObject::make('API')->setVersion('0.0.1'))
        ->addPath(new Path('hidden'))
        ->addPath(new Path('pets'))
        ->addPath((new Path('pets'))->addOperation(Operation::make('get')->summary('List pets')))
        ->addPath(new Path('pets'))
        ->addPath((new Path('pets'))->addOperation(Operation::make('post')->summary('Create pet')));

    $document = serializeAsVersion($openApi, $version);

    expect(json_encode($document['paths'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))->toBe($expected);
})->with([
    [OpenApiVersion::V3_1, '{"/hidden":{},"/pets":{"get":{"summary":"List pets"},"post":{"summary":"Create pet"}}}'],
    [OpenApiVersion::V3_2, '{"/hidden":{},"/pets":{"get":{"summary":"List pets"},"post":{"summary":"Create pet"}}}'],
]);
