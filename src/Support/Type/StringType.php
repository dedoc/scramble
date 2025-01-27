<?php

namespace Dedoc\Scramble\Support\Type;

class StringType extends AbstractType
{
    public function __construct(
        ?string $format = null
    ) {
        if ($format != null) {
            $this->setAttribute('format', $format);
        }
    }

    public function isSame(Type $type)
    {
        return $type instanceof static;
    }

    public function toString(): string
    {
        return 'string';
    }
}
