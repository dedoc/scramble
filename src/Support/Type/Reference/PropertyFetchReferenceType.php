<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

class PropertyFetchReferenceType extends AbstractReferenceType
{
    public function __construct(
        public Type $object,
        public string $propertyName,
        public bool $isNullsafe = false,
    ) {}

    public function nodes(): array
    {
        return ['object'];
    }

    public function toString(): string
    {
        $op = $this->isNullsafe ? '?.' : '.';

        return "(#{$this->object->toString()}){$op}{$this->propertyName}";
    }
}
