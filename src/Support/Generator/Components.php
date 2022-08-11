<?php

namespace Dedoc\Documentor\Support\Generator;

class Components
{
    /** @var array<string, Schema> */
    public array $schemas = [];

    public function hasSchema(string $schemaName): bool
    {
        return array_key_exists($schemaName, $this->schemas);
    }

    public function addSchema(string $schemaName, Schema $schema): self
    {
        $this->schemas[$schemaName] = $schema;

        return $this;
    }

    public function toArray()
    {
        $result = [];

        if (count($this->schemas)) {
            $result['schemas'] = array_map(
                fn (Schema $s) => $s->toArray(),
                $this->schemas
            );
        }

        return $result;
    }
}
