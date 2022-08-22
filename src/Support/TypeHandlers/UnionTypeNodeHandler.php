<?php

namespace Dedoc\Scramble\Support\TypeHandlers;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\NullType;
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
        $types = array_filter(array_map(
            fn ($t) => TypeHandlers::handle($t),
            $this->node->types,
        ));

        if (count($types) === 2 && collect($types)->contains(fn (Type $t) => $t instanceof NullType)) {
            $nonNullType = collect($types)->reject(fn (Type $t) => $t instanceof NullType)->first();

            return $nonNullType->nullable(true);
        }

        return (new AnyOf)->setItems($types);
    }
}
