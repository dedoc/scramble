<?php

namespace Dedoc\Scramble\Support\TypeHandlers;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;

interface PhpDocTypeVisitor
{
    public function enter(TypeNode $type): void;

    public function leave(TypeNode $type): void;
}
