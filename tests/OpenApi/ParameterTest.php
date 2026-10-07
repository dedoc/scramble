<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Parameter;

it('serializes parameter content with a media type reference for the target version', function (OpenApiVersion $version, array $expected) {
    $reference = (new Components)->addMediaType('Filter', (new MediaType)->setExample(['active' => true]));
    $parameter = (new Parameter('filter', 'query'))->addContent('application/json', $reference);

    expect(serializeAsVersion($parameter, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V31, [
        'name' => 'filter',
        'in' => 'query',
        'content' => ['application/json' => ['example' => ['active' => true]]],
    ]],
    [OpenApiVersion::V32, [
        'name' => 'filter',
        'in' => 'query',
        'content' => ['application/json' => ['$ref' => '#/components/mediaTypes/Filter']],
    ]],
]);
