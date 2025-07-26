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

    public function clone(): self
    {
        $cloned = clone $this;

        foreach ($cloned->nodes() as $nodeName) {
            $nodeValue = $cloned->$nodeName;

            if ($nodeValue instanceof TemplateType) {
                continue;
            }

            if (! is_array($nodeValue)) {
                $cloned->$nodeName = $nodeValue->clone();

                continue;
            }

            foreach ($nodeValue as $i => $nodeItemValue) {
                if ($nodeItemValue instanceof TemplateType) {
                    continue;
                }

                $cloned->$nodeName[$i] = $nodeItemValue->clone();
            }
        }

        return $cloned;
    }

    //    public function __clone(): void
    //    {
    //        foreach ($this->nodes() as $nodeName) {
    //            $nodeValue = $this->$nodeName;
    //
    //            if (! is_array($nodeValue)) {
    //                $this->$nodeName = clone $nodeValue;
    //
    //                continue;
    //            }
    //
    //            foreach ($nodeValue as $i => $nodeItemValue) {
    //                $this->$nodeName[$i] = clone $nodeItemValue;
    //            }
    //        }
    //    }
}
