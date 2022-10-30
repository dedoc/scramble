<?php

namespace Dedoc\Scramble\Support\Type;

class Generic extends AbstractType
{
    public ObjectType $type;

    public array $genericTypes;

    public function __construct(ObjectType $type, array $genericTypes)
    {
        $this->type = $type;
        $this->genericTypes = $genericTypes;
    }

    public function isInstanceOf(string $className)
    {
        return $this->type->isInstanceOf($className);
    }

    public function getPropertyFetchType(string $propertyName): Type
    {
        return $this->type->getPropertyFetchType($propertyName);
    }

    public function children(): array
    {
        return [
            $this->type,
            ...$this->genericTypes,
        ];
    }

    public function nodes(): array
    {
        return ['type', 'genericTypes'];
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
