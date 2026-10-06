<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;

it('serializes a response with a summary and media type reference for the target version', function (OpenApiVersion $version, array $expected) {
    $components = (new Components)->setMediaTypes([
        'Payload' => (new MediaType)->setExample(['id' => 1]),
    ]);
    $response = Response::make(200)
        ->setDescription('The requested payload')
        ->setSummary('Payload returned')
        ->addContent('application/json', new Reference('mediaTypes', 'Payload', $components));

    expect(serializeAsVersion($response, $version))->toBe($expected);
})->with([
    [
        OpenApiVersion::V31,
        [
            'description' => 'The requested payload',
            'content' => ['application/json' => ['example' => ['id' => 1]]],
        ],
    ],
    [
        OpenApiVersion::V32,
        [
            'description' => 'The requested payload',
            'summary' => 'Payload returned',
            'content' => ['application/json' => ['$ref' => '#/components/mediaTypes/Payload']],
        ],
    ],
]);

it('serializes URI references as schema references', function (OpenApiVersion $version) {
    $reference = Reference::fromUri('https://example.com/definitions.json#/Payload');
    $response = Response::make(200)
        ->setContent('application/json', $reference)
        ->addContent('application/xml', $reference);

    expect(serializeAsVersion($response, $version))->toBe([
        'description' => '',
        'content' => [
            'application/json' => ['schema' => ['$ref' => 'https://example.com/definitions.json#/Payload']],
            'application/xml' => ['schema' => ['$ref' => 'https://example.com/definitions.json#/Payload']],
        ],
    ]);
})->with([OpenApiVersion::V31, OpenApiVersion::V32]);
