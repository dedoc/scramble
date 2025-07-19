<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

class CallableCallReferenceType extends AbstractReferenceType
{
    public function __construct(
        public Type $callee,
        /** @var Type[] $arguments */
        public array $arguments,
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

        $calleeString = is_string($this->callee) ? $this->callee : $this->callee->toString();

        return "(λ{$calleeString})($argsTypes)";
    }
}
