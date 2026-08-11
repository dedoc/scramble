<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

class MethodCallReferenceType extends AbstractReferenceType
{
    public function __construct(
        public Type $callee,
        public string $methodName,
        /** @var Type[] $arguments */
        public array $arguments,
        public bool $isNullsafe = false,
    ) {}

    public function nodes(): array
    {
        return ['callee', 'arguments'];
    }

    public function toString(): string
    {
        $argsTypes = implode(
            ', ',
            array_map(fn ($t) => $t->toString(), $this->arguments),
        );

        $op = $this->isNullsafe ? '?.' : '.';

        return "(#{$this->callee->toString()}){$op}{$this->methodName}($argsTypes)";
    }
}
