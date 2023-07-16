<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\Dependency\MethodDependency;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\Type;

class MethodCallReferenceType extends AbstractReferenceType
{
    public function __construct(
        public Type $callee,
        public string $methodName,
        /** @var Type[] $arguments */
        public array $arguments,
    ) {
    }

    public function toString(): string
    {
        $argsTypes = implode(
            ', ',
            array_map(fn ($t) => $t->toString(), $this->arguments),
        );

        return "(#{$this->callee->toString()}).{$this->methodName}($argsTypes)";
    }

    public function dependencies(): array
    {
        if ($this->callee instanceof AbstractReferenceType) {
            return $this->callee->dependencies();
        }

        if (! $this->callee instanceof ObjectType && ! $this->callee instanceof SelfType) {
            return [];
        }

        return [
            new MethodDependency($this->callee->name, $this->methodName),
        ];
    }
}
