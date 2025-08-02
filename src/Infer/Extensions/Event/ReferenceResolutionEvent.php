<?php

namespace Dedoc\Scramble\Infer\Extensions\Event;

use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Type;

class ReferenceResolutionEvent
{
    public function __construct(
        public Type $type,
        public ReferenceTypeResolver $resolver,
    )
    {
    }
}
