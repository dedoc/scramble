<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class LazyArgumentTypeBag implements ArgumentTypeBag
{
    public function __construct(private Scope $scope, private array $arguments)
    {
    }

    public function get(string $name, int $position, ?Type $default = new UnknownType): ?Type
    {
        if ($argumentType = $this->arguments[$name] ?? $this->arguments[$position] ?? null) {
            return ReferenceTypeResolver::getInstance()->resolve($this->scope, $argumentType);
        }

        return $default;
    }

    public function all(): array
    {
        return array_map(
            fn ($t) => ReferenceTypeResolver::getInstance()->resolve($this->scope, $t),
            $this->arguments,
        );
    }

    public function count(): int
    {
        return count($this->arguments);
    }
}
