<?php

namespace Dedoc\Scramble\Infer\Query;

use Dedoc\Scramble\Support\Type\ObjectType;

class TrackedInstance
{
    public function __construct(
        private readonly ObjectType $type,
        private readonly CallSite $origin,
    ) {}

    public function type(): ObjectType
    {
        return $this->type;
    }

    public function origin(): CallSite
    {
        return $this->origin;
    }
}
