<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;

class ConditionalType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $subject,
        public Type $checkType,
        public Type $ifTrue,
        public Type $ifFalse,
    ) {}

    public function nodes(): array
    {
        return ['subject', 'checkType', 'ifTrue', 'ifFalse'];
    }

    public function resolve(): Type
    {
        return static::matches($this->subject, $this->checkType)
            ? $this->ifTrue
            : $this->ifFalse;
    }

    public function isResolvable(): bool
    {
        return TypeHelper::isResolvable($this->subject)
            && TypeHelper::isResolvable($this->checkType)
            && TypeHelper::isResolvable($this->ifTrue)
            && TypeHelper::isResolvable($this->ifFalse);
    }

    public function isSame(Type $type): bool
    {
        return $type instanceof static
            && $this->subject->isSame($type->subject)
            && $this->checkType->isSame($type->checkType)
            && $this->ifTrue->isSame($type->ifTrue)
            && $this->ifFalse->isSame($type->ifFalse);
    }

    public function toString(): string
    {
        return sprintf(
            '(%s is %s ? %s : %s)',
            $this->subject->toString(),
            $this->checkType->toString(),
            $this->ifTrue->toString(),
            $this->ifFalse->toString(),
        );
    }

    private static function matches(Type $subject, Type $checkType): bool
    {
        return $subject->acceptedBy($checkType);
    }
}
