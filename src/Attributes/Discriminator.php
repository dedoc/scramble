<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Documents a class or an interface as a `oneOf` schema of the mapped types.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Discriminator
{
    /**
     * @param  array<array-key, class-string>  $mapping  Class names of the documented types, optionally keyed by the values of the discriminator property.
     */
    public function __construct(
        public readonly string $propertyName,
        public readonly array $mapping = [],
    ) {}
}
