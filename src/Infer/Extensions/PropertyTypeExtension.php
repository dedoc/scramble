<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

interface PropertyTypeExtension extends InferExtension
{
    public function shouldHandle(ObjectType $type): bool;

    public function getPropertyType(PropertyFetchEvent $event): ?Type;
}
