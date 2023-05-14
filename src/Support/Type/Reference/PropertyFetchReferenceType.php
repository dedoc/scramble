<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\Dependency\PropertyDependency;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\Type;

class PropertyFetchReferenceType extends AbstractReferenceType
{
    public function __construct(
        public Type $object,
        public string $propertyName,
    ) {
    }

    public function toString(): string
    {
        return "(#{$this->object->toString()}).{$this->propertyName}";
    }

    public function dependencies(): array
    {
        if ($this->object instanceof AbstractReferenceType) {
            return $this->object->dependencies();
        }

        if (! $this->object instanceof ObjectType && ! $this->object instanceof SelfType) {
            return [];
        }

        return [
            new PropertyDependency($this->object->name, $this->propertyName),
        ];
    }
}
