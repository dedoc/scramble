<?php

namespace Dedoc\Scramble\Infer;

class Symbol
{
    const KIND_FUNCTION = 0;
    const KIND_CLASS = 1;
    const KIND_CLASS_METHOD = 2;
    const KIND_CONSTANT = 3;

    public function __construct(
        public readonly string $name,
        public readonly int $kind,
        public readonly ?string $className = null,
    )
    {
    }

    public static function createForFunction(string $name)
    {
        return new self($name, static::KIND_FUNCTION);
    }

    public static function createForClass(string $name)
    {
        return new self($name, static::KIND_CLASS);
    }

    public static function createForMethod(string $className, string $name)
    {
        return new self($name, static::KIND_CLASS_METHOD, $className);
    }
}
