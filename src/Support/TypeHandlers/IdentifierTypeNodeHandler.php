<?php

namespace Dedoc\ApiDocs\Support\TypeHandlers;

use Dedoc\ApiDocs\Support\Generator\Types\ArrayType;
use Dedoc\ApiDocs\Support\Generator\Types\BooleanType;
use Dedoc\ApiDocs\Support\Generator\Types\IntegerType;
use Dedoc\ApiDocs\Support\Generator\Types\NullType;
use Dedoc\ApiDocs\Support\Generator\Types\NumberType;
use Dedoc\ApiDocs\Support\Generator\Types\ObjectType;
use Dedoc\ApiDocs\Support\Generator\Types\StringType;
use Dedoc\ApiDocs\Support\Generator\Types\Type;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

class IdentifierTypeNodeHandler implements TypeHandler
{
    public IdentifierTypeNode $node;

    public function __construct(IdentifierTypeNode $node)
    {
        $this->node = $node;
    }

    public static function shouldHandle($node)
    {
        return $node instanceof IdentifierTypeNode;
    }

    public function handle(): ?Type
    {
        if ($this->node->name === 'string') {
            return new StringType;
        }

        if (in_array($this->node->name, ['float', 'double'])) {
            return new NumberType;
        }

        if (in_array($this->node->name, ['int', 'integer'])) {
            return new IntegerType;
        }

        if (in_array($this->node->name, ['bool', 'boolean', 'true', 'false'])) {
            return new BooleanType;
        }

        if ($this->node->name === 'scalar') {
            // @todo: Scalar variables are those containing an int, float, string or bool.
            return new StringType;
        }

        if ($this->node->name === 'array') {
            return new ArrayType;
        }

        if ($this->node->name === 'object') {
            return new ObjectType;
        }

        if ($this->node->name === 'null') {
            return new NullType;
        }

        if ($type = TypeHandlers::handleIdentifier($this->node->name)) {
            return $type;
        }

        return null;
    }
}
