<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;

interface AfterClassDefinitionCreatedExtension extends InferExtension
{
    public function shouldHandle(string $name): bool;

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event);
}
