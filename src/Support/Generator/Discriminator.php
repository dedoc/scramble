<?php

namespace Dedoc\Scramble\Support\Generator;

/**
 * @see https://spec.openapis.org/oas/v3.1.0#discriminator-object
 */
class Discriminator
{
    /**
     * @param  array<string, Reference|string>  $mapping
     */
    public function __construct(
        public string $propertyName,
        public array $mapping = [],
    ) {}

    public function clone(): static
    {
        $clone = clone $this;

        $clone->mapping = array_map(
            fn (Reference|string $value) => $value instanceof Reference ? $value->clone() : $value,
            $clone->mapping,
        );

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['propertyName' => $this->propertyName];

        if ($this->mapping) {
            $result['mapping'] = array_map(
                fn (Reference|string $value) => $value instanceof Reference ? $value->getReferenceUri() : $value,
                $this->mapping,
            );
        }

        return $result;
    }
}
