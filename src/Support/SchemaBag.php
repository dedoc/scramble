<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Support\Generator\Types\Type;

class SchemaBag
{
    /**
     * @param  array<string, Type>  $items
     */
    public function __construct(
        private array $items = []
    ) {}

    public function get(string $name): ?Type
    {
        return $this->items[$name] ?? null;
    }

    public function set(string $name, Type $schema): self
    {
        $this->items[$name] = $schema;

        return $this;
    }

    /** @return array<string, Type> */
    public function all(): array
    {
        return $this->items;
    }
}
