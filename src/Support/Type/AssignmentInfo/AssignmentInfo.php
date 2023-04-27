<?php

namespace Dedoc\Scramble\Support\Type\AssignmentInfo;

use Dedoc\Scramble\Support\Type\Type;

interface AssignmentInfo
{
    public function getAssignedType(): Type;
}
