<?php

namespace Dedoc\Scramble\PhpDoc;

use PhpParser\NameContext;
use PhpParser\Node\Name;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

class ResolveFqnPhpDocTypeVisitor extends AbstractPhpDocTypeVisitor
{
    private NameContext $context;

    public function __construct(NameContext $context)
    {
        $this->context = $context;
    }

    public function enter(TypeNode $type): void
    {
        if ($type instanceof IdentifierTypeNode) {
            $type->name = $this->context->getResolvedName(new Name([$type->name]), 1)->toString();
        }
    }
}
