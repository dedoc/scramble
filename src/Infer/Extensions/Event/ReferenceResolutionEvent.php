<?php

namespace Dedoc\Scramble\Infer\Extensions\Event;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Support\Type\Type;

class ReferenceResolutionEvent
{
    public function __construct(
        public Type $type,
    ) {}
}
