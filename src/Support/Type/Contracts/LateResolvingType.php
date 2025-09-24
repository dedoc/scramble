<?php

namespace Dedoc\Scramble\Support\Type\Contracts;

use Dedoc\Scramble\Support\Type\Type;

/**
 * This is the type which resolution is posponed until the internal template type is known.
 */
interface LateResolvingType extends Type
{
    public function resolve(): Type;

    public function isResolvable(): bool;
}
