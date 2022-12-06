<?php

namespace Dedoc\Scramble\Support\Generator\Types;

class UnknownType extends StringType
{
    // @phpstan-ignore-next-line
    private string $comment;

    public function __construct(string $comment = '')
    {
        parent::__construct();
        $this->comment = $comment;
    }
}
