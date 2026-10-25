<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\AbstractType;
use Dedoc\Scramble\Support\Type\Type;

/** A structural call description, resolved only by its owning call chain. */
class MethodCall extends AbstractType
{
    public function __construct(
        public string $methodName,
        /** @var array<array-key, Type> */
        public array $arguments,
    ) {}

    public function nodes(): array
    {
        return ['arguments'];
    }

    public function isSame(Type $type)
    {
        return $type instanceof self && $this->toString() === $type->toString();
    }

    public function toString(): string
    {
        $arguments = implode(', ', array_map(fn (Type $type) => $type->toString(), $this->arguments));

        return "{$this->methodName}($arguments)";
    }
}
