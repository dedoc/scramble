<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

interface ArgumentsBag
{
    public function getArgument(ArgumentPosition $position, ?Type $default = new UnknownType): ?Type;

    public function all(): array;
}
