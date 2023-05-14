<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

interface MethodReturnTypeExtension extends InferExtension
{
    public function shouldHandle(ObjectType $type): bool;

    public function getMethodReturnType(MethodCallEvent $event): ?Type;
}
