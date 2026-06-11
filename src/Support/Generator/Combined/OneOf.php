<?php

namespace Dedoc\Scramble\Support\Generator\Combined;

use Dedoc\Scramble\Support\Generator\Example;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use InvalidArgumentException;

class OneOf extends Type
{
    use HasCombinedItems;

    public string $combinedOperator = 'oneOf';

    /** @var Type[] */
    public $items;

    public function __construct()
    {
        parent::__construct('oneOf');
        $this->items = [new StringType];
    }

    public function clone(): static
    {
        $clone = parent::clone();
        $clone->items = array_map(
            fn (Type $item) => $item->clone(),
            $clone->items,
        );

        return $clone;
    }

    public function setItems($items)
    {
        if (collect($items)->contains(fn ($item) => ! $item instanceof Type)) {
            throw new InvalidArgumentException('All items should be instances of '.Type::class);
        }

        $this->items = $items;

        return $this;
    }
}
