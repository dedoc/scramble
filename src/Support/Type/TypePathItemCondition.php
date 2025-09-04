<?php

namespace Dedoc\Scramble\Support\Type;

class TypePathItemCondition
{
    public function __construct(
        public ?string $class = null,
        public ?string $objectName = null,
    ) {}

    public function matches(Type $result): bool
    {
        if ($this->class && $this->class !== $result::class) {
            return false;
        }

        if ($this->objectName) {
            return $result instanceof ObjectType && $result->isInstanceOf($this->objectName);
        }

        return true;
    }
}
