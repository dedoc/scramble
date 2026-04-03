<?php

namespace Dedoc\Scramble\Reflection;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Support\Arr;

class ReflectionModel
{
    private function __construct(public readonly string $name, public readonly ClassDefinition $definition)
    {
    }

    public static function createForClass(string $class): self
    {
        return new self($class, app(Infer::class)->analyzeClass($class));
    }

    public function isKeyUuid(): bool
    {
        $modelTraits = class_uses_recursive($this->name);

        return Arr::hasAny($modelTraits, [HasUuids::class, HasVersion4Uuids::class]);
    }
}
