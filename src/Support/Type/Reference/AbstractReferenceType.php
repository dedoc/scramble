<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\AbstractType;
use Dedoc\Scramble\Support\Type\Reference\Dependency\Dependency;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\Type;

abstract class AbstractReferenceType extends AbstractType
{
    public function isSame(Type $type)
    {
        if (! $type instanceof static) {
            return false;
        }

        // @todo: revisit, maybe this either not optimal or there is a better way.
        return $type->toString() === $this->toString();
    }
}
