<?php

namespace Dedoc\Scramble\Support\Generator;

use Illuminate\Support\Str;

class Components
{
    /** @var array<string, Schema> */
    public array $schemas = [];

    /** @var array<string, SecurityScheme> */
    public array $securitySchemes = [];

    // @todo: figure out how to solve the problem of duplicating resource names better
    public array $tempNames = [];

    public function addSecurityScheme(string $name, SecurityScheme $securityScheme)
    {
        $this->securitySchemes[$name] = $securityScheme;

        return $this;
    }

    public function hasSchema(string $schemaName): bool
    {
        return array_key_exists($schemaName, $this->schemas);
    }

    public function addSchema(string $schemaName, Schema $schema): Reference
    {
        $this->schemas[$schemaName] = $schema;

        return new Reference('schemas', $schemaName, $this);
    }

    public function removeSchema(string $schemaName): void
    {
        unset($this->schemas[$schemaName]);
    }

    public function toArray()
    {
        $result = [];

        if (count($this->securitySchemes)) {
            $result['securitySchemes'] = collect($this->securitySchemes)
                ->map(fn (SecurityScheme $s) => $s->toArray())
                ->toArray();
        }

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

            return static::slug($shortestPossibleName);
        }

        return static::slug($fullName);
    }

    public function getSchemaReference(string $schemaName)
    {
        return new Reference('schemas', $schemaName, $this);
    }

    public function getSchema(string $schemaName)
    {
        return $this->schemas[$schemaName];
    }

    public static function slug(string $name)
    {
        return Str::replace('\\', '.', $name);
    }
}
