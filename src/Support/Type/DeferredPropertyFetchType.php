<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;

class DeferredPropertyFetchType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $object,
        public string $propertyName,
        private Scope $scope,
        public bool $isNullsafe = false,
        private ?Type $fallback = null,
    ) {}

    public function nodes(): array
    {
        return ['object'];
    }

    public function resolve(): Type
    {
        $type = ReferenceTypeResolver::getInstance()->resolve(
            $this->scope,
            new PropertyFetchReferenceType($this->object, $this->propertyName, $this->isNullsafe),
        );

        if ($this->fallback && (
            $type instanceof UnknownType
            || ($type->accepts($this->fallback) && ! $this->fallback->accepts($type))
        )) {
            return $this->fallback;
        }

        return $type;
    }

    public function isResolvable(): bool
    {
        return TypeHelper::isResolvable($this->object);
    }

    public function isSame(Type $type): bool
    {
        return false;
    }

    public function toString(): string
    {
        $op = $this->isNullsafe ? '?.' : '.';

        return "({$this->object->toString()}){$op}{$this->propertyName}";
    }
}
