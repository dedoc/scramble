<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;

abstract class AbstractType implements Type
{
    use TypeAttributes;

    /**
     * @deprecated Will be removed in the next BC-containing release.
     */
    public ?Type $original = null;

    /**
     * @deprecated Will be removed in the next BC-containing release.
     *
     * @return $this
     */
    public function setOriginal(?Type $original): self
    {
        $this->original = $original; // @phpstan-ignore property.deprecated

        return $this;
    }

    /**
     * @deprecated Will be removed in the next BC-containing release.
     */
    public function getOriginal(): ?Type
    {
        return $this->original; // @phpstan-ignore property.deprecated
    }

    public function nodes(): array
    {
        return [];
    }

    public function isInstanceOf(string $className): bool
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

    public function intersect(Type $otherType): Type
    {
        if ($this->accepts($otherType)) {
            return $otherType;
        }

        if ($otherType->accepts($this)) {
            return $this;
        }

        return new NeverType;
    }

    public function getPropertyType(string $propertyName, Scope $scope): Type
    {
        $className = $this::class;

        return new UnknownType("Cannot get a property type [$propertyName] on type [{$className}]");
    }

    public function getOffsetValueType(Type $offset): Type
    {
        return new UnknownType('Cannot get an offset value type '.$this::class);
    }

    public function widen(): Type
    {
        return $this;
    }

    public function getMethodDefinition(string $methodName, Scope $scope = new GlobalScope): ?FunctionLikeDefinition
    {
        return null;
    }

    /**
     * Creates a deep clone of the type.
     *
     * Note that template types are not cloned but rather kept as is due to templates replacement in the generics
     * is made via reference comparison, not just via names. For now.
     */
    public function clone(): static
    {
        return $this->cloneWithContext(new \WeakMap);
    }

    /** @param  \WeakMap<object, mixed>  $clones */
    private function cloneWithContext(\WeakMap $clones): static
    {
        if (isset($clones[$this]) && $clones[$this] instanceof static) {
            return $clones[$this];
        }

        $cloned = clone $this;
        $clones[$this] = $cloned;

        foreach ($cloned->nodes() as $nodeName) {
            /** @var Type|Type[] $nodeValue */
            $nodeValue = $cloned->$nodeName;

            if ($nodeValue instanceof TemplateType) {
                continue;
            }

            if (! is_array($nodeValue)) {
                $cloned->$nodeName = $nodeValue instanceof self
                    ? $nodeValue->cloneWithContext($clones)
                    : $nodeValue->clone();

                continue;
            }

            /** @var Type $nodeItemValue */
            foreach ($nodeValue as $i => $nodeItemValue) {
                if ($nodeItemValue instanceof TemplateType) {
                    continue;
                }

                $cloned->$nodeName[$i] = $nodeItemValue instanceof self
                    ? $nodeItemValue->cloneWithContext($clones)
                    : $nodeItemValue->clone();
            }
        }

        return $cloned;
    }
}
