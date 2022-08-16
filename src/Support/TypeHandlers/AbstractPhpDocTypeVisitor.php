<?php

namespace Dedoc\Documentor\Support\TypeHandlers;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;

class AbstractPhpDocTypeVisitor implements PhpDocTypeVisitor
{
    public function enter(TypeNode $type): void
    {
    }

    public function leave(TypeNode $type): void
    {
    }
}
