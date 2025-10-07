<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Countable;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

interface ArgumentTypeBag extends Countable
{
    public function get(string $name, int $position, ?Type $default = new UnknownType): ?Type;

    /** @return array<array-key, Type> */
    public function all(): array;

    /**
     * @param  callable(Type, string|int): Type  $cb
     */
    public function map(callable $cb): self;
}
