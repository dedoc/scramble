<?php

namespace Dedoc\ApiDocs\Support\Generator;

class Components
{
    /** @var array<string, Schema> */
    public array $schemas = [];

    // @todo: figure out how to solve the problem of duplicating resource names better
    public array $tempNames = [];

    public function hasSchema(string $schemaName): bool
    {
        return array_key_exists($schemaName, $this->schemas);
    }

    public function addSchema(string $schemaName, Schema $schema): Reference
    {
        $this->schemas[$schemaName] = $schema;

        return new Reference('schemas', $schemaName, $this);
    }

    public function toArray()
    {
        $result = [];

        if (count($this->schemas)) {
            $result['schemas'] = collect($this->schemas)
                ->mapWithKeys(function (Schema $s, string $fullName) {
                    return [
                        $this->uniqueSchemaName($fullName) => $s->setTitle($this->uniqueSchemaName($fullName))->toArray(),
                    ];
                })
                ->toArray();
        }

        return $result;
    }

    public function uniqueSchemaName(string $fullName)
    {
        $shortestPossibleName = class_basename($fullName);

        if (
            ($this->tempNames[$shortestPossibleName] ?? null) === null
            || ($this->tempNames[$shortestPossibleName] ?? null) === $fullName
        ) {
            $this->tempNames[$shortestPossibleName] = $fullName;

            return $shortestPossibleName;
        }

        return $fullName;
    }

    public function getSchemaReference(string $schemaName)
    {
        return new Reference('schemas', $schemaName, $this);
    }

    public function getSchema(string $schemaName)
    {
        return $this->schemas[$schemaName];
    }
}
