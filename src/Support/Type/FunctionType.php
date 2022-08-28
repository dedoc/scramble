<?php

namespace Dedoc\Scramble\Support\Type;

class FunctionType extends AbstractType implements FunctionLikeType
{
    public array $arguments;

    public Type $returnType;

    public function __construct(
        $arguments = [],
        $returnType = null
    ) {
        $this->arguments = $arguments;
        $this->returnType = $returnType ?: new VoidType();
    }

    public function setReturnType(Type $type): self
    {
        $this->returnType = $type;
        return $this;
    }

    public function getReturnType(): Type
    {
        return $this->returnType;
    }

    public function isSame(Type $type)
    {
        return $type instanceof static
            && $this->returnType->isSame($type->returnType)
            && collect($this->arguments)->every(fn (Type $t, $i) => $t->isSame($type->arguments[$i]));
    }

    public function toString(): string
    {
        return sprintf(
            '(%s): %s',
            implode(', ', array_map(fn ($t) => $t->toString(), $this->arguments)),
            $this->returnType->toString(),
        );
    }
}
