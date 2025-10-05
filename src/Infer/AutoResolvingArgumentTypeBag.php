<?php

namespace Dedoc\Scramble\Infer;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class AutoResolvingArgumentTypeBag implements ArgumentTypeBag
{
    /**
     * @param  array<array-key, Type>  $arguments
     */
    public function __construct(private Scope $scope, private array $arguments) {}

    public function get(string $name, int $position, ?Type $default = new UnknownType): ?Type
    {
        if ($argumentType = $this->arguments[$name] ?? $this->arguments[$position] ?? null) {
            return ReferenceTypeResolver::getInstance()->resolve($this->scope, $argumentType);
        }

        return $default;
    }

    public function map(callable $cb): ArgumentTypeBag
    {
        return new self(
            $this->scope,
            collect($this->arguments)->map(fn ($t, $key) => $cb($t, $key))->all(),
        );
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

    /** @return array<array-key, Type> */
    public function allUnresolved(): array
    {
        return $this->arguments;
    }
}
