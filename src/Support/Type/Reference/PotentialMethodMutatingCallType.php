<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

class PotentialMethodMutatingCallType extends AbstractReferenceType
{
    public function __construct(
        public Type $subject,
        /** @var MethodCall[] Calls in one expression, in execution order. */
        public array $calls,
    ) {}

    public function nodes(): array
    {
        return ['subject', 'calls'];
    }

    public function isInstanceOf(string $className): bool
    {
        return $this->subject->isInstanceOf($className);
    }

    public function toString(): string
    {
        $calls = implode('.', array_map(fn (MethodCall $call) => $call->toString(), $this->calls));

        return "self-out-(#{$this->subject->toString()}).{$calls}";
    }
}
