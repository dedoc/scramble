<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class LazyArgumentsBag implements ArgumentsBag
{
    public function __construct(private Scope $scope, private array $arguments) {}

    public function getArgument(ArgumentPosition $position, ?Type $default = new UnknownType): ?Type
    {
        if ($argumentType = $this->arguments[$position->name] ?? $this->arguments[$position->index] ?? null) {
            return ReferenceTypeResolver::getInstance()->resolve($this->scope, $argumentType);
        }

        return $default;
    }

    public function all(): array
    {
        return $this->arguments;
    }
}
