<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Reference\Dependency\ClassDependency;
use Dedoc\Scramble\Support\Type\Type;

class NewCallReferenceType extends AbstractReferenceType
{
    public function __construct(
        public string $name,
        /** @var Type[] $arguments */
        public array $arguments,
    ) {
    }

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }

    public function toString(): string
    {
        $argsTypes = implode(
            ', ',
            array_map(fn ($t) => $t->toString(), $this->arguments),
        );

        return "(new {$this->name})($argsTypes)";
    }

    public function dependencies(): array
    {
        return [
            new ClassDependency($this->name),
        ];
    }
}
