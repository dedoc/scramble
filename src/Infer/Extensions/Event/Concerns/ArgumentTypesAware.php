<?php

namespace Dedoc\Scramble\Infer\Extensions\Event\Concerns;

use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

trait ArgumentTypesAware
{
    public function getArg(string $name, int $position, Type $default = new UnknownType): Type
    {
        return $this->arguments->get($name, $position, $default) ?: $default;
    }
}
