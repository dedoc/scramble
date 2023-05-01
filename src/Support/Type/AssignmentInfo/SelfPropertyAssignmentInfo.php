<?php

namespace Dedoc\Scramble\Support\Type\AssignmentInfo;

use Dedoc\Scramble\Support\Type\Type;

class SelfPropertyAssignmentInfo implements AssignmentInfo
{
    public function __construct(
        public string $property,
        public Type $type,
    ) {
    }

    public function getAssignedType(): Type
    {
        return $this->type;
    }
}
