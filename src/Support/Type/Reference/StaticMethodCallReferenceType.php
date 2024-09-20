<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Reference\Dependency\MethodDependency;
use Dedoc\Scramble\Support\Type\Type;

class StaticMethodCallReferenceType extends AbstractReferenceType
{
    public function __construct(
        public string $callee,
        public string $methodName,
        /** @var Type[] $arguments */
        public array $arguments,
    ) {}

    public function nodes(): array
    {
        return ['arguments'];
    }

    public function toString(): string
    {
        $argsTypes = implode(
            ', ',
            array_map(fn ($t) => $t->toString(), $this->arguments),
        );

        return "(#{$this->callee})::{$this->methodName}($argsTypes)";
    }

    public function dependencies(): array
    {
        return [
            new MethodDependency($this->callee, $this->methodName),
        ];
    }
}
