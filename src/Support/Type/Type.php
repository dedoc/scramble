<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;

interface Type
{
    public function setAttribute(string $key, $value): void;

    public function hasAttribute(string $key): bool;

    public function getAttribute(string $key);

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function mergeAttributes(array $attributes): static;

    public function isInstanceOf(string $className): bool;

    public function accepts(Type $otherType): bool;

    public function acceptedBy(Type $otherType): bool;

    /**
     * @return string[]
     */
    public function nodes(): array;

    public function getPropertyType(string $propertyName, Scope $scope): Type;

    public function getOffsetValueType(Type $offset): Type;

    public function getMethodDefinition(string $methodName, Scope $scope = new GlobalScope): ?FunctionLikeDefinition;

    /** @return $this */
    public function setOriginal(?Type $original): self;

    public function getOriginal(): ?Type;

    public function widen(): Type;

    /**
     * @return bool
     */
    public function isSame(self $type);

    public function toString(): string;

    public function clone(): static;
}
