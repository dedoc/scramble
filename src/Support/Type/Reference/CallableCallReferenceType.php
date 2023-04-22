<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

class CallableCallReferenceType extends AbstractReferenceType
{
    public function __construct(
        public string $callee,
        /** @var Type[] $arguments */
        public array $arguments,
    ){}

    public function toString(): string
    {
        $argsTypes = implode(
            ', ',
            array_map(fn ($t) => $t->toString(), $this->arguments),
        );

        return "(λ{$this->callee})($argsTypes)";
    }
}
