<?php

use Dedoc\Scramble\Scramble;

it('resolves null security strategy', function () {
    $config = Scramble::configure()->useConfig(array_merge(config('scramble'), [
        'security_strategy' => null,
    ]));

    expect($config->securityStrategy())->toBeNull();
});

it('throws for invalid security strategy config', function () {
    $config = Scramble::configure()->useConfig(array_merge(config('scramble'), [
        'security_strategy' => 123,
    ]));

    $config->securityStrategy();
})->throws(InvalidArgumentException::class);
