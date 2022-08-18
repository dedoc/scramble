<?php

namespace Dedoc\ApiDocs\Support\TypeHandlers;

use Dedoc\ApiDocs\Support\Generator\Types\ArrayType;
use Dedoc\ApiDocs\Support\Generator\Types\Type;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;

class ArrayTypeNodeHandler implements TypeHandler
{
    public ArrayTypeNode $node;

    public function __construct(ArrayTypeNode $node)
    {
        $this->node = $node;
    }

    public static function shouldHandle($node)
    {
        return $node instanceof ArrayTypeNode;
    }

    public function handle(): ?Type
    {
        return (new ArrayType)->setItems(TypeHandlers::handle($this->node->type));
    }
}
