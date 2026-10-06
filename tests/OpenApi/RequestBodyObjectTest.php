<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;

it('serializes a request body with a media type reference for the target version', function (OpenApiVersion $version, array $expected) {
    $components = (new Components)->setMediaTypes([
        'Payload' => (new MediaType)->setExample(['id' => 1]),
    ]);
    $requestBody = RequestBodyObject::make()
        ->required()
        ->addContent('application/json', new Reference('mediaTypes', 'Payload', $components));

    expect(serializeAsVersion($requestBody, $version))->toBe($expected);
})->with([
    [
        OpenApiVersion::V31,
        [
            'required' => true,
            'content' => ['application/json' => ['example' => ['id' => 1]]],
        ],
    ],
    [
        OpenApiVersion::V32,
        [
            'required' => true,
            'content' => ['application/json' => ['$ref' => '#/components/mediaTypes/Payload']],
        ],
    ],
]);

it('serializes URI references as schema references', function (OpenApiVersion $version) {
    $reference = Reference::fromUri('https://example.com/definitions.json#/Payload');
    $requestBody = RequestBodyObject::make()
        ->setContent('application/json', $reference)
        ->addContent('application/xml', $reference);

    expect(serializeAsVersion($requestBody, $version))->toBe([
        'content' => [
            'application/json' => ['schema' => ['$ref' => 'https://example.com/definitions.json#/Payload']],
            'application/xml' => ['schema' => ['$ref' => 'https://example.com/definitions.json#/Payload']],
        ],
    ]);
})->with([OpenApiVersion::V31, OpenApiVersion::V32]);
