<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Tag;

it('serializes a tag for the target version', function (OpenApiVersion $version, array $expected) {
    $tag = (new Tag('partner', description: 'Operations available to partners'))
        ->setSummary('Partner')
        ->setParent('external')
        ->setKind('audience');

    expect(serializeAsVersion($tag, $version))->toBe($expected);
})->with([
    [OpenApiVersion::V31, [
        'name' => 'partner',
        'description' => 'Operations available to partners',
    ]],
    [OpenApiVersion::V32, [
        'name' => 'partner',
        'description' => 'Operations available to partners',
        'summary' => 'Partner',
        'parent' => 'external',
        'kind' => 'audience',
    ]],
]);
