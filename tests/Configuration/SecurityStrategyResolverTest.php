<?php

use Dedoc\Scramble\Configuration\SecurityStrategyResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

it('resolves null security strategy', function () {
    $config = Scramble::configure()->useConfig(array_merge(config('scramble'), [
        'security_strategy' => null,
    ]));

    expect(SecurityStrategyResolver::resolve($config))->toBeNull();
});

it('resolves class-string security strategy', function () {
    $config = Scramble::configure()->useConfig(config('scramble'));

    expect(SecurityStrategyResolver::resolve($config))
        ->toBeInstanceOf(MiddlewareAuthSecurityStrategy::class);
});

it('resolves security strategy with options', function () {
    $config = Scramble::configure()->useConfig(array_merge(config('scramble'), [
        'security_strategy' => [
            MiddlewareAuthSecurityStrategy::class,
            ['authMiddleware' => 'auth:api'],
        ],
    ]));

    $strategy = SecurityStrategyResolver::resolve($config);

    expect($strategy)->toBeInstanceOf(MiddlewareAuthSecurityStrategy::class)
        ->and($strategy->triggerMiddleware)->toBe('auth:api');
});

it('throws for invalid security strategy config', function () {
    $config = Scramble::configure()->useConfig(array_merge(config('scramble'), [
        'security_strategy' => 123,
    ]));

    SecurityStrategyResolver::resolve($config);
})->throws(InvalidArgumentException::class);
