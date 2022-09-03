<?php

namespace Dedoc\Scramble\PhpDoc;

use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

class ResolveFqnPhpDocTypeVisitor extends AbstractPhpDocTypeVisitor
{
    private $nameResolver;

    public function __construct(callable $nameResolver)
    {
        $this->nameResolver = $nameResolver;
    }

    public function enter(TypeNode $type): void
    {
        if ($type instanceof IdentifierTypeNode) {
            $type->name = ($this->nameResolver)($type->name);
        }
    }
}
