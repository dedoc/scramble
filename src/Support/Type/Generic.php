<?php

namespace Dedoc\Scramble\Support\Type;

class Generic extends AbstractType
{
    public Identifier $type;

    public array $genericTypes;

    public function __construct(Identifier $type, array $genericTypes)
    {
        $this->type = $type;
        $this->genericTypes = $genericTypes;
    }

    public function isInstanceOf(string $className)
    {
        return is_a($this->type->name, $className, true);
    }

    public function isSame(Type $type)
    {
        return $type instanceof static
            && $this->type->isSame($type->type)
            && collect($this->genericTypes)->every(fn (Type $t, $i) => $t->isSame($type->genericTypes[$i]));
    }

    public function toString(): string
    {
        return sprintf(
            '%s<%s>',
            $this->type->toString(),
            implode(', ', array_map(fn ($t) => $t->toString(), $this->genericTypes))
        );
    }
}
