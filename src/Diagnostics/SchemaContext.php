<?php

namespace Dedoc\Scramble\Diagnostics;

class SchemaContext
{
    public function __construct(
        public string $name,
        public ?string $class = null,
    ) {}

    public static function createFromJsonPointer(string $jsonPointer, ?string $sourceClass): ?self
    {
        if (! preg_match('#^/components/schemas/([^/]+)#', $jsonPointer, $matches)) {
            return null;
        }

        $name = $matches[1];
        $class = $sourceClass && class_exists($sourceClass) ? $sourceClass : null;

        return new self($name, $class);
    }
}
