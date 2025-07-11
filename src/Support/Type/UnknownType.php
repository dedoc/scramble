<?php

namespace Dedoc\Scramble\Support\Type;

class UnknownType extends AbstractType
{
    public ?Type $original = null;

    public function __construct(
        private string $comment = '',
        ?Type $original = null,
    ) {
        $this->original = $original;
    }

    public function nodes(): array
    {
        return ['original'];
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'unknown';
    }
}
