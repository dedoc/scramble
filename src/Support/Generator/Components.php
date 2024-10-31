<?php

namespace Dedoc\Scramble\Support\Generator;

use Illuminate\Support\Str;
use InvalidArgumentException;

class Components
{
    /** @var array<string, Schema> */
    public array $schemas = [];

    /** @var array<string, Response> */
    public array $responses = [];

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
                ->sortKeys()
                ->toArray();
        }

        if (count($this->responses)) {
            $result['responses'] = collect($this->responses)
                ->mapWithKeys(function (Response $r, string $fullName) {
                    return [
                        $this->uniqueSchemaName($fullName) => $r->toArray(),
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

    public function has(Reference $reference): bool
    {
        $this->ensureValidReference($reference);

        return array_key_exists($reference->fullName, $this->{$reference->referenceType});
    }

    public function add(Reference $reference, $object): Reference
    {
        $this->ensureValidReference($reference, $object);

        $this->{$reference->referenceType}[$reference->fullName] = $object;

        return $reference;
    }

    public function get(Reference $reference)
    {
        $this->ensureValidReference($reference);

        return $this->{$reference->referenceType}[$reference->fullName];
    }

    private function ensureValidReference(Reference $reference, $object = null)
    {
        $references = [
            'schemas' => Schema::class,
            'responses' => Response::class,
        ];

        if (! in_array($reference->referenceType, $referenceTypes = array_keys($references))) {
            $validTypes = implode(', ', $referenceTypes);

            throw new InvalidArgumentException("Only $validTypes references are allowed");
        }

        if ($object === null) {
            return;
        }

        $expectedType = $references[$reference->referenceType];

        if (! is_a($object, $expectedType)) {
            $actualType = get_class($object);

            throw new InvalidArgumentException("Object must be $expectedType to be added to $reference->referenceType references, $actualType given");
        }
    }
}
