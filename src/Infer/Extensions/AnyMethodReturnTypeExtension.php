<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\AnyMethodCallEvent;
use Dedoc\Scramble\Support\Type\Type;

interface AnyMethodReturnTypeExtension extends InferExtension
{
    public function getMethodReturnType(AnyMethodCallEvent $event): ?Type;
}
