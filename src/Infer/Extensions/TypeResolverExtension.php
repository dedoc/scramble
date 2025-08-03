<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Support\Type\Type;

/** @internal */
interface TypeResolverExtension extends InferExtension
{
    public function resolve(ReferenceResolutionEvent $event): ?Type;
}
