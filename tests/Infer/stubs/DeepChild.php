<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class DeepChild extends Child
{
    public function __construct(string $bar, string $wow)
    {
        parent::__construct($bar, $wow, 'doesntmatter');
    }
}
