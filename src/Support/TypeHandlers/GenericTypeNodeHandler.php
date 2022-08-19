<?php

namespace Dedoc\Scramble\Support\TypeHandlers;

use Dedoc\Scramble\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Identifier;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

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

        if (! collect($this->node->genericTypes)->every(fn ($t) => $t instanceof IdentifierTypeNode || $t instanceof GenericTypeNode)) {
            return null; // @todo: unknown type with reason
        }

        return ComplexTypeHandlers::handle($this->phpDocTypeToType($this->node));
    }

    private function phpDocTypeToType(TypeNode $type)
    {
        if ($type instanceof IdentifierTypeNode) {
            return new Identifier($type->name);
        }

        if ($type instanceof GenericTypeNode) {
            return new Generic(
                $this->phpDocTypeToType($type->type),
                array_map(
                    fn ($type) => $this->phpDocTypeToType($type),
                    $type->genericTypes,
                ),
            );
        }
    }
}
