<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

/**
 * @internal
 *
 * @template T of object
 */
class PublicProxy
{
    /** @var T */
    private object $target;

    /**
     * @param  T  $target
     */
    public function __construct(object $target)
    {
        $this->target = $target;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $method = new \ReflectionMethod($this->target, $name);

        return $method->invokeArgs($this->target, $arguments);
    }

    public function __get(string $name): mixed
    {
        $prop = new \ReflectionProperty($this->target, $name);

        return $prop->getValue($this->target);
    }

    /**
     * @param  mixed  $value
     */
    public function __set(string $name, $value): void
    {
        $prop = new \ReflectionProperty($this->target, $name);

        $prop->setValue($this->target, $value);
    }
}
