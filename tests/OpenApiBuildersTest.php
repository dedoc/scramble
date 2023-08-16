<?php

use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow;

use function Spatie\Snapshots\assertMatchesSnapshot;

it('builds security scheme', function () {
    $openApi = (new OpenApi('3.1.0'))
        ->setInfo(InfoObject::make('API')->setVersion('0.0.1'));

    $openApi->secure(SecurityScheme::apiKey('query', 'api_token'));
    $document = $openApi->toArray();

    expect($document['security'])->toBe([['apiKey' => []]])
        ->and($document['components']['securitySchemes'])->toBe([
            'apiKey' => [
                'type' => 'apiKey',
                'in' => 'query',
                'name' => 'api_token',
            ],
        ]);
});

it('builds oauth2 security scheme', function () {
    $openApi = (new OpenApi('3.1.0'))
        ->setInfo(InfoObject::make('API')->setVersion('0.0.1'));

    $openApi->secure(
        SecurityScheme::oauth2()
            ->flow('implicit', function (OAuthFlow $flow) {
                $flow
                    ->refreshUrl('https://test.com')
                    ->tokenUrl('https://test.com/token')
                    ->addScope('wow', 'nice');
            })
    );

    assertMatchesSnapshot($openApi->toArray());
});
