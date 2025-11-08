<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Support\Generator\Types\Type as Schema;

class SchemaBag
{
    /**
     * @param  array<string, Schema>  $items
     */
    public function __construct(
        private array $items = []
    ) {}

    public function get(string $name): ?Schema
    {
        return $this->items[$name] ?? null;
    }

    public function set(string $name, Schema $schema): self
    {
        $this->items[$name] = $schema;

        return $this;
    }

    /** @return array<string, Schema> */
    public function all(): array
    {
        return $this->items;
    }
}
