<?php

use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

it('builds security scheme', function () {
    $openApi = (new OpenApi('3.1.0'))
        ->setInfo(InfoObject::make('API')->setVersion('0.0.1'));

    $openApi->secure(SecurityScheme::apiKey('query', 'api_token')->default());
    $document = $openApi->toArray();

    expect($document['security'])->toBe([['apiKey' => []]])
        ->and($document['components']['securitySchemes'])->toBe([
            'apiKey' => [
                'type' => 'apiKey',
                'in' => 'query',
                'name' => 'api_token',
            ]
        ]);
});
