<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

class PropertyAssignReferenceType extends AbstractReferenceType
{
    public function __construct(
        public Type $object,
        public string $propertyName,
        public Type $value,
    ) {}

    public function nodes(): array
    {
        return ['object', 'value'];
    }

    public function toString(): string
    {
        return "(#{$this->object->toString()}).{$this->propertyName} = {$this->value->toString()}";
    }
}
