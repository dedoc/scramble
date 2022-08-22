<?php

namespace Dedoc\Scramble\Support\TypeHandlers;

use Dedoc\Scramble\Support\Generator\Combined\OneOf;
use Dedoc\Scramble\Support\Generator\Types\Type;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class UnionTypeNodeHandler implements TypeHandler
{
    public UnionTypeNode $node;

    public function __construct(UnionTypeNode $node)
    {
        $this->node = $node;
    }

    public static function shouldHandle($node)
    {
        return $node instanceof UnionTypeNode;
    }

    public function handle(): ?Type
    {
        return (new OneOf)->setItems(array_filter(array_map(
            fn ($t) => TypeHandlers::handle($t),
            $this->node->types,
        )));
    }
}
