<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Support\Type\Type;

interface StaticMethodReturnTypeExtension extends InferExtension
{
    public function shouldHandle(string $name): bool;

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type;
}
