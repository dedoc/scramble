<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Closure;
use Illuminate\Support\Optional;

class UnevaluatedValue extends Optional
{
    public function __construct(private Closure $onUse)
    {
    }

    public function __get($key)
    {
        return tap(parent::__get($key), $this->onUse);
    }

    public function __isset($name)
    {
        return tap(parent::__isset($name), $this->onUse);
    }

    public function __call($method, $parameters)
    {
        return tap(parent::__call($method, $parameters), $this->onUse);
    }
}
