<?php

namespace Dedoc\Scramble\Support\Type;

class Generic implements Type
{
    public Identifier $type;

    public array $genericTypes;

    public function __construct(Identifier $type, array $genericTypes)
    {
        $this->type = $type;
        $this->genericTypes = $genericTypes;
    }
}
