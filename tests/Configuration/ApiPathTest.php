<?php

use Dedoc\Scramble\Configuration\ApiPath;

it('matches routes with string prefix', function () {
    $apiPath = ApiPath::from('api');

    expect($apiPath->matches('api/users'))->toBeTrue()
        ->and($apiPath->matches('api'))->toBeTrue()
        ->and($apiPath->matches('apiary/users'))->toBeFalse()
        ->and($apiPath->matches('second-api/users'))->toBeFalse();
});

it('matches all routes when prefix is empty string', function () {
    $apiPath = ApiPath::from('');

    expect($apiPath->matches('api/users'))->toBeTrue()
        ->and($apiPath->matches('second-api/users'))->toBeTrue();
});

it('matches routes with include and exclude', function () {
    $apiPath = ApiPath::from([
        'include' => 'api',
        'exclude' => ['api/internal'],
    ]);

    expect($apiPath->matches('api/users'))->toBeTrue()
        ->and($apiPath->matches('api/internal/secret'))->toBeFalse()
        ->and($apiPath->matches('api/internalfoo'))->toBeTrue()
        ->and($apiPath->matches('second-api/users'))->toBeFalse();
});

it('defaults include to api when only exclude is provided', function () {
    $apiPath = ApiPath::from([
        'exclude' => ['api/internal'],
    ]);

    expect($apiPath->matches('api/users'))->toBeTrue()
        ->and($apiPath->matches('api/internal/secret'))->toBeFalse()
        ->and($apiPath->matches('second-api/users'))->toBeFalse();
});

it('matches routes with multiple includes', function () {
    $apiPath = ApiPath::from([
        'include' => ['api', 'second-api'],
    ]);

    expect($apiPath->matches('api/users'))->toBeTrue()
        ->and($apiPath->matches('second-api/users'))->toBeTrue()
        ->and($apiPath->matches('admin/users'))->toBeFalse();
});

it('matches all routes when include is empty', function () {
    $apiPath = ApiPath::from([
        'include' => '',
        'exclude' => ['api/internal'],
    ]);

    expect($apiPath->matches('api/users'))->toBeTrue()
        ->and($apiPath->matches('api/internal/secret'))->toBeFalse()
        ->and($apiPath->matches('second-api/users'))->toBeTrue();
});

it('uses default api path when config is null', function () {
    $apiPath = ApiPath::from(null);

    expect($apiPath->matches('api/users'))->toBeTrue()
        ->and($apiPath->matches('second-api/users'))->toBeFalse();
});

it('strips prefix for single include', function () {
    $apiPath = ApiPath::from('api');

    expect($apiPath->stripPrefix('api/users'))->toBe('users');
});

it('does not strip prefix for multiple includes', function () {
    $apiPath = ApiPath::from([
        'include' => ['api', 'second-api'],
    ]);

    expect($apiPath->stripPrefix('api/users'))->toBe('api/users')
        ->and($apiPath->stripPrefix('second-api/users'))->toBe('second-api/users');
});

it('does not strip prefix when prefix is empty', function () {
    $apiPath = ApiPath::from('');

    expect($apiPath->stripPrefix('api/users'))->toBe('api/users');
});

it('resolves server path for string prefix', function () {
    expect(ApiPath::from('api')->serverPath())->toBe('api')
        ->and(ApiPath::from('')->serverPath())->toBe('');
});

it('resolves server path for array config', function () {
    expect(ApiPath::from([
        'include' => 'api',
        'exclude' => ['api/internal'],
    ])->serverPath())->toBe('api')
        ->and(ApiPath::from([
            'include' => ['api', 'second-api'],
        ])->serverPath())->toBe('')
        ->and(ApiPath::from([
            'include' => '',
            'exclude' => ['api/internal'],
        ])->serverPath())->toBe('');
});

it('throws for invalid array config shape', function () {
    ApiPath::from(['includes' => 'api']);
})->throws(InvalidArgumentException::class, 'Invalid scramble.api_path config');

it('throws for numeric list config shape', function () {
    ApiPath::from(['api', 'v2']);
})->throws(InvalidArgumentException::class, 'Invalid scramble.api_path config');

it('throws for non-string non-array config', function () {
    ApiPath::from(123);
})->throws(InvalidArgumentException::class, 'Invalid scramble.api_path config');

it('matches routes with wildcard include pattern', function () {
    $apiPath = ApiPath::from([
        'include' => 'api/v*',
    ]);

    expect($apiPath->matches('api/v1/users'))->toBeTrue()
        ->and($apiPath->matches('api/v2/users'))->toBeTrue()
        ->and($apiPath->matches('api/users'))->toBeFalse()
        ->and($apiPath->matches('api/legacy/users'))->toBeFalse();
});

it('matches routes with wildcard exclude pattern', function () {
    $apiPath = ApiPath::from([
        'include' => 'api',
        'exclude' => ['api/internal/*'],
    ]);

    expect($apiPath->matches('api/users'))->toBeTrue()
        ->and($apiPath->matches('api/internal/secret'))->toBeFalse()
        ->and($apiPath->matches('api/internalfoo'))->toBeTrue();
});

it('does not strip prefix or set server path for wildcard include', function () {
    $apiPath = ApiPath::from([
        'include' => 'api/v*',
    ]);

    expect($apiPath->serverPath())->toBe('')
        ->and($apiPath->stripPrefix('api/v1/users'))->toBe('api/v1/users');
});
