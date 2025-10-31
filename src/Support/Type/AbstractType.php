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
        $cloned = clone $this;

        foreach ($cloned->nodes() as $nodeName) {
            /** @var Type|Type[] $nodeValue */
            $nodeValue = $cloned->$nodeName;

            if ($nodeValue instanceof TemplateType) {
                continue;
            }

            if (! is_array($nodeValue)) {
                $cloned->$nodeName = $nodeValue->clone();

                continue;
            }

            /** @var Type $nodeItemValue */
            foreach ($nodeValue as $i => $nodeItemValue) {
                if ($nodeItemValue instanceof TemplateType) {
                    continue;
                }

                $cloned->$nodeName[$i] = $nodeItemValue->clone();
            }
        }

        return $cloned;
    }
}
