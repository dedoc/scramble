<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Callback;
use Dedoc\Scramble\Support\Generator\Path;

it('serializes an empty callback as an object', function (OpenApiVersion $version, string $expected) {
    expect(json_encode(serializeAsVersion(new Callback, $version), JSON_THROW_ON_ERROR))->toBe($expected);
})->with([
    [OpenApiVersion::V31, '{}'],
    [OpenApiVersion::V32, '{}'],
]);

it('serializes an empty callback path item as an object', function (OpenApiVersion $version, string $expected) {
    $callback = (new Callback)->addPath('{$request.body#/url}', new Path(''));

    expect(json_encode(serializeAsVersion($callback, $version), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))->toBe($expected);
})->with([
    [OpenApiVersion::V31, '{"{$request.body#/url}":{}}'],
    [OpenApiVersion::V32, '{"{$request.body#/url}":{}}'],
]);
