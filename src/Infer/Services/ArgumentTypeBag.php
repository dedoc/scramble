<?php

namespace Dedoc\Scramble\Infer\Services;

use Countable;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

interface ArgumentTypeBag extends Countable
{
    public function get(string $name, int $position, ?Type $default = new UnknownType): ?Type;

    public function all(): array;
}
