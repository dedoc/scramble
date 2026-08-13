<?php

namespace Dedoc\Scramble\Support\Generator\Combined;

use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use InvalidArgumentException;

/**
 * Base for the schemas combining other schemas: `allOf`, `anyOf`, and `oneOf`.
 */
abstract class CombinedType extends Type
{
    /** @var Type[] */
    public $items;

    public ?Discriminator $discriminator = null;

    public function __construct(string $type)
    {
        parent::__construct($type);

        $this->items = [new StringType];
    }

    public function clone(): static
    {
        $clone = parent::clone();

        $clone->items = array_map(
            fn (Type $item) => $item->clone(),
            $clone->items,
        );

        $clone->discriminator = $clone->discriminator?->clone();

        return $clone;
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return $this
     */
    public function setItems($items)
    {
        foreach ($items as $item) {
            if (! $item instanceof Type) {
                throw new InvalidArgumentException('All items should be instances of '.Type::class);
            }
        }

        /** @var Type[] $items */
        $this->items = $items;

        return $this;
    }

    public function setDiscriminator(?Discriminator $discriminator): static
    {
        $this->discriminator = $discriminator;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $parentArray = parent::toArray();

        unset($parentArray['type']);

        $result = [
            ...$parentArray,
            $this->type => array_map(
                fn (Type $item) => $item->toArray(),
                $this->items,
            ),
        ];

        if ($this->discriminator) {
            $result['discriminator'] = $this->discriminator->toArray();
        }

        return $result;
    }
}
