<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;

class ConditionalType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $subject,
        public Type $checkType,
        public Type $ifTrue,
        public Type $ifFalse,
        public bool $negated = false,
    ) {}

    public function nodes(): array
    {
        return ['subject', 'checkType', 'ifTrue', 'ifFalse'];
    }

    public function acceptedBy(Type $otherType): bool
    {
        return ($otherType->accepts($this->ifTrue) || $this->ifTrue->acceptedBy($otherType))
            && ($otherType->accepts($this->ifFalse) || $this->ifFalse->acceptedBy($otherType));
    }

    public function resolve(): Type
    {
        /*
         * @todo
         * Ideally, here we need to have yes/no/maybe logic. Conditions below replicate this
         * logic for simpler case.
         */
        if ($this->checkType instanceof LiteralBooleanType && $this->subject instanceof BooleanType) {
            if (! $this->subject instanceof LiteralBooleanType) {
                return Union::wrap([$this->ifTrue, $this->ifFalse]);
            }

            $matches = $this->subject->value === $this->checkType->value;
        } else {
            $matches = $this->matches($this->subject, $this->checkType);
        }

        return $matches !== $this->negated
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
            && $this->negated === $type->negated
            && $this->subject->isSame($type->subject)
            && $this->checkType->isSame($type->checkType)
            && $this->ifTrue->isSame($type->ifTrue)
            && $this->ifFalse->isSame($type->ifFalse);
    }

    public function toString(): string
    {
        return sprintf(
            '(%s %s %s ? %s : %s)',
            $this->subject->toString(),
            $this->negated ? 'is not' : 'is',
            $this->checkType->toString(),
            $this->ifTrue->toString(),
            $this->ifFalse->toString(),
        );
    }

    private function matches(Type $subject, Type $checkType): bool
    {
        return $subject->acceptedBy($checkType);
    }
}
