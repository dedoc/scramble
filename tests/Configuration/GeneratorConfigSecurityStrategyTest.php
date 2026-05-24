<?php

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

it('resolves null security strategy', function () {
    $config = Scramble::configure()->useConfig(array_merge(config('scramble'), [
        'security_strategy' => null,
    ]));

    expect($config->securityStrategy())->toBeNull();
});

it('resolves class-string security strategy', function () {
    $config = Scramble::configure()->useConfig(array_merge(config('scramble'), [
        'security_strategy' => MiddlewareAuthSecurityStrategy::class,
    ]));

    expect($config->securityStrategy())
        ->toBeInstanceOf(MiddlewareAuthSecurityStrategy::class);
});

it('throws for invalid security strategy config', function () {
    $config = Scramble::configure()->useConfig(array_merge(config('scramble'), [
        'security_strategy' => 123,
    ]));

    $config->securityStrategy();
})->throws(InvalidArgumentException::class);
