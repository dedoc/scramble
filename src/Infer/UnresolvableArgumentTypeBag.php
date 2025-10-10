<?php

namespace Dedoc\Scramble\Infer;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class UnresolvableArgumentTypeBag implements ArgumentTypeBag
{
    /**
     * @param  array<array-key, Type>  $arguments
     */
    public function __construct(private array $arguments) {}

    public function get(string $name, int $position, ?Type $default = new UnknownType): ?Type
    {
        return $this->arguments[$name] ?? $this->arguments[$position] ?? $default;
    }

    public function map(callable $cb): ArgumentTypeBag
    {
        return new self(collect($this->arguments)->map(fn ($t, $key) => $cb($t, $key))->all());
    }

    public function all(): array
    {
        return $this->arguments;
    }

    public function count(): int
    {
        return count($this->arguments);
    }
}
