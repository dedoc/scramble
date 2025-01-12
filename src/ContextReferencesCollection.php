<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Support\Generator\Reference;
use Illuminate\Support\Str;

/** @internal */
class ContextReferencesCollection
{
    private array $tempNames = [];

    /**
     * @param array<string, Reference> $items The key is reference ID and the value is a reference.
     */
    public function __construct(
        public array $items = [],
    )
    {
    }

    public function has(string $referenceId): bool
    {
        return array_key_exists($referenceId, $this->items);
    }

    public function get(string $referenceId): ?Reference
    {
        return $this->items[$referenceId] ?? null;
    }

    public function set(string $referenceId, Reference $reference): void
    {
        $this->items[$referenceId] = $reference;
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

    public function add(string $referenceId, Reference $reference): Reference
    {
        $referenceName = $reference->shortName ?: $this->uniqueSchemaName($reference->fullName);

        $reference = clone $reference;
        $reference->fullName = $referenceName;

        $this->items[$referenceId] = $reference;

        return $reference;
    }

    public function uniqueName(string $referenceId): string
    {
        if ($this->has($referenceId)) {
            return $this->get($referenceId)->shortName;
        }

        return $this->uniqueSchemaName($referenceId);
    }

    public function remove(string $referenceId): void
    {
        unset($this->items[$referenceId]);
    }
}
