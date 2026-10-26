<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

/**
 * @property-read Type $callee Deprecated. Use $subject and $calls instead.
 * @property-read string $methodName Deprecated. Use $calls instead.
 */
class PotentialMethodMutatingCallType extends AbstractReferenceType
{
    public function __construct(
        public Type $subject,
        /** @var MethodCall[] Calls in one expression, in execution order. */
        public array $calls,
    ) {}

    /** @deprecated Use $subject and $calls instead. */
    public function __get(string $name): mixed
    {
        if ($name === 'methodName') {
            return $this->calls[array_key_last($this->calls)]->methodName;
        }

        if ($name === 'callee') {
            $callee = $this->subject;

            foreach (array_slice($this->calls, 0, -1) as $call) {
                $callee = new MethodCallReferenceType($callee, $call->methodName, $call->arguments);
            }

            return $callee;
        }

        trigger_error('Undefined property: '.self::class.'::$'.$name, E_USER_WARNING);

        return null;
    }

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
