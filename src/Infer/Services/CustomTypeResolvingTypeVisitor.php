<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Support\Type\AbstractTypeVisitor;
use Dedoc\Scramble\Support\Type\Type;

class CustomTypeResolvingTypeVisitor extends AbstractTypeVisitor
{
    public function leave(Type $type): ?Type
    {
        if ($newType = Context::getInstance()->extensionsBroker->getResolvedType(new ReferenceResolutionEvent($type))) {
            return $newType;
        }

        return null;
    }
}
