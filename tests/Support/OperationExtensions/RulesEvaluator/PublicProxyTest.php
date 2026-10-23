<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\PublicProxy;

it('reaches non-public methods and properties of the target', function () {
    $target = new PublicProxyTest_Target;
    $proxy = new PublicProxy($target);

    expect($proxy->secret)->toBe('initial');

    $proxy->secret = 'changed';

    expect($proxy->reveal('!'))->toBe('changed!');
});

class PublicProxyTest_Target
{
    private string $secret = 'initial';

    protected function reveal(string $suffix): string
    {
        return $this->secret.$suffix;
    }
}
