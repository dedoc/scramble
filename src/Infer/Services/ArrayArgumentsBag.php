<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class ArrayArgumentsBag implements ArgumentsBag
{
    public function __construct(private array $arguments)
    {
    }

    public function getArgument(ArgumentPosition $position, ?Type $default = new UnknownType): ?Type
    {
        return $this->arguments[$position->name] ?? $this->arguments[$position->index] ?? $default;
    }

    public function all(): array
    {
        return $this->arguments;
    }
}
