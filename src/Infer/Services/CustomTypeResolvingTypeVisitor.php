<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\AutoResolvingArgumentTypeBag;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
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
        if ($argumentsType = $this->resolveArguments($t)) {
            return $argumentsType;
        }

        $event = new ReferenceResolutionEvent(type: $t);

        if ($newType = Context::getInstance()->extensionsBroker->getResolvedType($event)) {
            if ($newType !== $t) {
                return $newType;
            }
        }

        return null;
    }

    private function resolveArguments(Type $type): ?Type
    {
        if (! isset($this->originalType->arguments)) {
            return null;
        }

        if (! $type instanceof ObjectType) {
            return null;
        }

        if ($type->name !== 'Arguments') {
            return null;
        }

        $original = $this->originalType;

        if (
            $original instanceof CallableCallReferenceType
            && $original->callee instanceof CallableStringType
            && $original->callee->name === 'func_get_args'
        ) {
            return null;
        }

        $arguments = new AutoResolvingArgumentTypeBag($this->scope, $this->originalType->arguments);

        $arrayItems = collect($arguments->all())
            ->map(function (Type $type, $key) {
                return new ArrayItemType_($key, $type);
            })
            ->all();

        return new KeyedArrayType($arrayItems);
    }
}
