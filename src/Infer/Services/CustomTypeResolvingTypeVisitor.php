<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\AutoResolvingArgumentTypeBag;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Type;

class CustomTypeResolvingTypeVisitor
{
    public function __construct(
        private Type $originalType,
        private Scope $scope,
    ) {}

    public function enter(Type $type) {}

    public function leave(Type $t)
    {
        $event = new ReferenceResolutionEvent(
            type: $t,
            arguments: isset($this->originalType->arguments) ? new AutoResolvingArgumentTypeBag($this->scope, $this->originalType->arguments) : null
        );

        if ($newType = Context::getInstance()->extensionsBroker->getResolvedType($event)) {
            if ($newType !== $t) {
                return $newType;
            }
        }

        return null;
    }
}
