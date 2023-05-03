<?php

namespace Dedoc\Scramble\Support\Type;

class Generic extends ObjectType
{
    /**
     * The list of concrete types for this generic.
     *
     * @var array<int, Type>
     */
    public array $templateTypes = [];

    public function __construct(
        string $name,
        array $templateTypes = []
    ) {
        parent::__construct($name);
        if (! array_is_list($templateTypes)) {
            throw new \InvalidArgumentException('[$templateTypes] for Generic must be a list.');
        }
        $this->templateTypes = $templateTypes;
    }

    public function nodes(): array
    {
        return ['templateTypes'];
    }

    public function toString(): string
    {
        return sprintf(
            '%s<%s>',
            $this->name,
            implode(', ', array_map(fn ($t) => $t->toString(), $this->templateTypes))
        );
    }
}
