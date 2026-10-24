<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralType;

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
        return ($this->ifTrue instanceof NeverType || $otherType->accepts($this->ifTrue) || $this->ifTrue->acceptedBy($otherType))
            && ($this->ifFalse instanceof NeverType || $otherType->accepts($this->ifFalse) || $this->ifFalse->acceptedBy($otherType));
    }

    public function resolve(): Type
    {
        $matches = $this->matches($this->subject, $this->checkType);

        if ($matches === null) {
            return TypeHelper::mergeTypes($this->ifTrue, $this->ifFalse);
        }

        return $matches !== $this->negated
            ? $this->ifTrue
            : $this->ifFalse;
    }

    public function isResolvable(): bool
    {
        // Keep receiver-dependent conditions until self is bound at the call site.
        return ! $this->subject instanceof SelfType
            && TypeHelper::isResolvable($this->subject)
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

    /** Returns null when the subject can both match and not match. */
    private function matches(Type $subject, Type $checkType): ?bool
    {
        if ($subject instanceof Union) {
            $results = array_map(fn (Type $member) => $this->matches($member, $checkType), $subject->types);

            foreach ($results as $result) {
                if ($result !== $results[0]) {
                    return null;
                }
            }

            return $results[0] ?? null;
        }

        if ($checkType instanceof Union) {
            $results = array_map(fn (Type $member) => $this->matches($subject, $member), $checkType->types);

            return in_array(true, $results, true) ? true : (in_array(null, $results, true) ? null : false);
        }

        if ($subject instanceof MixedType) {
            return $checkType instanceof MixedType ? true : null;
        }

        if ($checkType instanceof LiteralType || $checkType instanceof EnumCaseType) {
            if ($checkType->isSame($subject)) {
                return true;
            }

            if ($subject instanceof LiteralType || $subject instanceof EnumCaseType) {
                return false;
            }

            return $subject->accepts($checkType) ? null : false;
        }

        if ($subject->acceptedBy($checkType)) {
            return true;
        }

        return $subject->accepts($checkType) ? null : false;
    }
}
