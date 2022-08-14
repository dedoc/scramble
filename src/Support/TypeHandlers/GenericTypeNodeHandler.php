<?php

namespace Dedoc\Documentor\Support\TypeHandlers;

use Dedoc\Documentor\Support\Generator\Types\Type;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;

class GenericTypeNodeHandler implements TypeHandler
{
    public GenericTypeNode $node;

    public function __construct(GenericTypeNode $node)
    {
        $this->node = $node;
    }

    public static function shouldHandle($node)
    {
        return $node instanceof GenericTypeNode;
    }

    public function handle(): ?Type
    {
        if ($this->node->type->name === 'array') {
            return TypeHandlers::handle(new ArrayShapeNode(
                array_map(
                    fn ($type) => new ArrayShapeItemNode(null, false, $type),
                    $this->node->genericTypes,
                )
            ));
        }

        return null; // @todo: unknown type with reason
    }
}
