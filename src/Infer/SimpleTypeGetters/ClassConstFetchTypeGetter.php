<?php

namespace Dedoc\Scramble\Infer\SimpleTypeGetters;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\ConstFetchReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class ClassConstFetchTypeGetter
{
    const STATIC_KEYWORDS = ['self', 'static', 'parent'];

    public function __invoke(Node\Expr\ClassConstFetch $node, Scope $scope): Type
    {
        if ($node->name->toString() === 'class') {
            if ($node->class instanceof Node\Name && in_array($node->class->toString(), static::STATIC_KEYWORDS)) {
                return new ConstFetchReferenceType(
                    new StaticReference($node->class->toString()),
                    'class',
                );
            }

            if ($node->class instanceof Node\Name) {
                return new LiteralStringType($node->class->toString());
            }

            $type = $scope->getType($node->class);

            if ($type instanceof ObjectType || $type instanceof NewCallReferenceType) {
                return new LiteralStringType($type->name);
            }

            return new StringType();
        }

        return new UnknownType('Cannot get type from class const fetch');
    }
}
