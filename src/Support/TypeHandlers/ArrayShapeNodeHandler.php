<?php

namespace Dedoc\Scramble\Support\TypeHandlers;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

class ArrayShapeNodeHandler implements TypeHandler
{
    public ArrayShapeNode $node;

    public function __construct(ArrayShapeNode $node)
    {
        $this->node = $node;
    }

    public static function shouldHandle($node)
    {
        return $node instanceof ArrayShapeNode;
    }

    public function handle(): ?Type
    {
        if ($this->isArrayShape()) {
            return (new ArrayType)->setItems(TypeHandlers::handle($this->node->items[1] ?? $this->node->items[0]));
        }

        if ($this->isMapType()) {
            return (new ObjectType)
                ->additionalProperties(
                    TypeHandlers::handle($this->node->items[1]->valueType) ?: new StringType
                );
        }

        $type = new ObjectType;
        $requiredKeys = [];

        foreach ($this->node->items as $arrayShapeItem) {
            $keyName = $arrayShapeItem->keyName instanceof IdentifierTypeNode
                ? $arrayShapeItem->keyName->name
                : $arrayShapeItem->keyName->value;

            if (! $arrayShapeItem->optional) {
                $requiredKeys[] = $keyName;
            }
            $type->addProperty($keyName, TypeHandlers::handle($arrayShapeItem->valueType) ?: new StringType);
        }
        $type->setRequired($requiredKeys);

        return $type;
    }

    private function isArrayShape()
    {
        // array{string}
        if (count($this->node->items) === 1 && $this->node->items[0]->keyName === null) {
            return true;
        }

        // array{int, string}
        if (
            count($this->node->items) === 2
            && $this->node->items[0]->keyName === null
            && $this->node->items[1]->keyName === null
            && ! $this->isMapType()
        ) {
            return true;
        }
    }

    private function isMapType()
    {
        return count($this->node->items) === 2
            && $this->node->items[0]->keyName === null
            && $this->node->items[1]->keyName === null
            && ($this->node->items[0]->valueType->name ?? 'string') === 'string';
    }
}
