<?php

namespace Dedoc\ApiDocs\Support\TypeHandlers;

use Dedoc\ApiDocs\Support\Generator\Types\Type;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;

class ArrayShapeItemNodeHandler implements TypeHandler
{
    public ArrayShapeItemNode $node;

    public function __construct(ArrayShapeItemNode $node)
    {
        $this->node = $node;
    }

    public static function shouldHandle($node)
    {
        return $node instanceof ArrayShapeItemNode;
    }

    public function handle(): ?Type
    {
        if ($this->node->keyName === null) {
            return TypeHandlers::handle($this->node->valueType);
        }

        return null;
    }
}
