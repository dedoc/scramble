<?php

namespace Dedoc\Scramble\Infer\Extensions\Event\Concerns;

use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

trait ArgumentTypesAware
{
    public function getArg(string $name, int $position, ?Type $default = null)
    {
        $default ??= new UnknownType();

        return $this->arguments[$name] ?? $this->arguments[$position] ?? $default;
    }
}
