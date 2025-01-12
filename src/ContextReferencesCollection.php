<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Support\Generator\Reference;
use Illuminate\Support\Str;

/** @internal */
class ContextReferencesCollection
{
    private array $tempNames = [];

    /**
     * @param  array<string, Reference[]>  $items  The key is reference ID and the value is a list of such references.
     */
    public function __construct(
        public array $items = [],
    ) {}

    public function has(string $referenceId): bool
    {
        return array_key_exists($referenceId, $this->items);
    }

    /**
     * @return Reference[]
     */
    public function get(string $referenceId): array
    {
        return $this->items[$referenceId] ?? [];
    }

    public function add(string $referenceId, Reference $reference): Reference
    {
        $this->items[$referenceId] ??= [];
        $this->items[$referenceId][] = $this->setUniqueName($reference);

        return $reference;
    }

    public function setUniqueName(Reference $reference): Reference
    {
        $reference->fullName = $reference->shortName ?: $this->uniqueSchemaName($reference->fullName);

        return $reference;
    }

    public function uniqueName(string $referenceId): string
    {
        if ($this->has($referenceId)) {
            $reference = $this->get($referenceId)[0];

            return $reference->shortName ?: $reference->fullName;
        }

        return $this->uniqueSchemaName($referenceId);
    }

    private function uniqueSchemaName(string $fullName)
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

    private static function slug(string $name)
    {
        return Str::replace('\\', '.', $name);
    }
}
