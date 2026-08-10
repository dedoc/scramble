<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

class DelayedNonInstantiableInstance
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }

    public function __get(string $name): mixed
    {
        return null;
    }

    /**
     * @param  mixed  $value
     */
    public function __set(string $name, $value): void {}
}
