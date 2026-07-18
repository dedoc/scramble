<?php

namespace Dedoc\Scramble\Infer\Definition;

use ReflectionAttribute;

class AttributeDefinition
{
    public const IS_INSTANCEOF = ReflectionAttribute::IS_INSTANCEOF;

    /**
     * @param  array<int|string, mixed>  $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {}

    /**
     * @param  ReflectionAttribute<object>[]  $reflectionAttributes
     * @return self[]
     */
    public static function fromReflectionAttributesArray(array $reflectionAttributes): array
    {
        return array_map(static::fromReflectionAttribute(...), $reflectionAttributes);
    }

    /**
     * @param  ReflectionAttribute<object>  $reflectionAttribute
     */
    public static function fromReflectionAttribute(ReflectionAttribute $reflectionAttribute): self
    {
        return new self(
            name: $reflectionAttribute->getName(),
            arguments: $reflectionAttribute->getArguments(),
        );
    }
}
