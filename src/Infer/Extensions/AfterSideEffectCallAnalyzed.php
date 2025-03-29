<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\SideEffectCallEvent;

interface AfterSideEffectCallAnalyzed extends InferExtension
{
    public function shouldHandle(SideEffectCallEvent $event): bool;

    public function afterSideEffectCallAnalyzed(SideEffectCallEvent $event);
}
