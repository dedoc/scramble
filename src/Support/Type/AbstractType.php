<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;

abstract class AbstractType implements Type
{
    use TypeAttributes;

    public ?Type $original = null;

    /** @return $this */
    public function setOriginal(?Type $original): self
    {
        $this->original = $original;

        return $this;
    }

    public function getOriginal(): ?Type
    {
        return $this->original;
    }

    public function nodes(): array
    {
        return [];
    }

    public function isInstanceOf(string $className)
    {
        return false;
    }

    public function accepts(Type $otherType): bool
    {
        return is_a($otherType::class, $this::class, true);
    }

    public function acceptedBy(Type $otherType): bool
    {
        return is_a($this::class, $otherType::class, true);
    }

    public function getPropertyType(string $propertyName, Scope $scope): Type
    {
        $className = $this::class;

        return new UnknownType("Cannot get a property type [$propertyName] on type [{$className}]");
    }

    public function getMethodDefinition(string $methodName, Scope $scope = new GlobalScope): ?FunctionLikeDefinition
    {
        return null;
    }
}
