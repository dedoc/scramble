<?php

declare(strict_types=1);

namespace Dedoc\Scramble\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OperationId
{
    public function __construct(public readonly string $id) {}
}
