<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;

class CoalesceType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $left,
        public Type $right,
    ) {}

    public function nodes(): array
    {
        return ['left', 'right'];
    }

    public function resolve(): Type
    {
        if (! TypeHelper::canContainNull($this->left)) {
            return $this->left;
        }

        return TypeHelper::mergeTypes(
            TypeHelper::withoutNull($this->left),
            $this->right,
        );
    }

    public function isResolvable(): bool
    {
        return TypeHelper::isResolvable($this->left)
            && TypeHelper::isResolvable($this->right);
    }

    public function isSame(Type $type)
    {
        return $type instanceof static
            && $this->left->isSame($type->left)
            && $this->right->isSame($type->right);
    }

    public function toString(): string
    {
        return $this->left->toString().'??'.$this->right->toString();
    }
}
