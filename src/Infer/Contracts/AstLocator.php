<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Symbol;
use PhpParser\Node;

interface AstLocator
{
    public function getSource(Symbol $symbol): ?Node;
}
