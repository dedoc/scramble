<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Support\Type\ObjectType;

interface MethodCallExceptionsExtension extends InferExtension
{
    public function shouldHandle(ObjectType $type): bool;

    /**
     * @return array<ObjectType>
     */
    public function getMethodCallExceptions(MethodCallEvent $event): array;
}
