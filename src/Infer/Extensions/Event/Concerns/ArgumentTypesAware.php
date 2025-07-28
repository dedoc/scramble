<?php

namespace Dedoc\Scramble\Infer\Extensions\Event\Concerns;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

trait ArgumentTypesAware
{
    public function getArg(string $name, int $position, Type $default = new UnknownType): Type
    {
        if ($this->arguments instanceof ArgumentTypeBag) {
            return $this->arguments->get($name, $position, $default);
        }

        return $this->arguments[$name] ?? $this->arguments[$position] ?? $default;
    }
}
