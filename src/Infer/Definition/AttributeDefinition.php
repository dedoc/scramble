<?php

namespace Dedoc\Scramble\Infer\Definition;

use ReflectionAttribute;

class AttributeDefinition
{
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {}

    /**
     * @param ReflectionAttribute[] $reflectionAttributes
     * @return self[]
     */
    public static function fromReflectionAttributesArray(array $reflectionAttributes): array
    {
        return array_map(static::fromReflectionAttribute(...), $reflectionAttributes);
    }

    public static function fromReflectionAttribute(ReflectionAttribute $reflectionAttribute): self
    {
        return new self(
            name: $reflectionAttribute->getName(),
            arguments: $reflectionAttribute->getArguments(),
        );
    }
}
