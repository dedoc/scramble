<?php

namespace Dedoc\Scramble\Support\TypeHandlers;

use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Types\Type;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;

class IntersectionTypeNodeHandler implements TypeHandler
{
    public IntersectionTypeNode $node;

    public function __construct(IntersectionTypeNode $node)
    {
        $this->node = $node;
    }

    public static function shouldHandle($node)
    {
        return $node instanceof IntersectionTypeNode;
    }

    public function handle(): ?Type
    {
        return (new AllOf)->setItems(array_filter(array_map(
            fn ($t) => TypeHandlers::handle($t),
            $this->node->types,
        )));
    }
}
