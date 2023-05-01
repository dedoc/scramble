<?php

namespace Dedoc\Scramble\Support\Type;

class Generic extends ObjectType
{
    /**
     * The map of template type names to concrete types.
     *
     * @var array<string, Type>
     */
    public array $templateTypesMap;

    public function __construct(string $name, array $templateTypesMap)
    {
        parent::__construct($name);
        $this->templateTypesMap = $templateTypesMap;
    }

    public function nodes(): array
    {
        return ['templateTypesMap'];
    }

    public function toString(): string
    {
        return sprintf(
            '%s<%s>',
            $this->name,
            implode(', ', array_map(fn ($t) => $t->toString(), $this->templateTypesMap))
        );
    }
}
