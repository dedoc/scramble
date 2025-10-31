<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

class NewCallReferenceType extends AbstractReferenceType
{
    public function __construct(
        public string|Type $name,
        /** @var Type[] $arguments */
        public array $arguments,
    ) {}

    public function nodes(): array
    {
        return ['arguments'];
    }

    public function isInstanceOf(string $className): bool
    {
        return is_string($this->name) && is_a($this->name, $className, true);
    }

    public function toString(): string
    {
        $argsTypes = implode(
            ', ',
            array_map(fn ($t) => $t->toString(), $this->arguments),
        );

        $name = is_string($this->name) ? $this->name : $this->name->toString();

        return "(new {$name})($argsTypes)";
    }
}
