<?php

namespace Dedoc\Scramble\Support\RuleTransforming;

use Dedoc\Scramble\Support\Generator\Types\Type;
use OutOfBoundsException;

class SchemaBag
{
    /**
     * @param  array<string, Type>  $items
     */
    public function __construct(
        private array $items = []
    ) {}

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->items);
    }

    public function get(string $name): ?Type
    {
        return $this->items[$name] ?? null;
    }

    public function getOrFail(string $name): Type
    {
        $item = $this->get($name);
        if (! $item) {
            throw new OutOfBoundsException("[$name] schema doesn't exist");
        }

        return $item;
    }

    public function set(string $name, Type $schema): self
    {
        $this->items[$name] = $schema;

        return $this;
    }

    public function first(): ?Type
    {
        return array_values($this->all())[0] ?? null;
    }

    public function firstOrFail(): Type
    {
        if (! $item = $this->first()) {
            throw new OutOfBoundsException("Schema bag doesn't have any item exist");
        }

        return $item;
    }

    public function remove(string $name): self
    {
        unset($this->items[$name]);

        return $this;
    }

    /** @return array<string, Type> */
    public function all(): array
    {
        return $this->items;
    }
}
