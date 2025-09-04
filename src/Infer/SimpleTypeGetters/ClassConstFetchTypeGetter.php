<?php

namespace Dedoc\Scramble\Infer\SimpleTypeGetters;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\ConstFetchReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class ClassConstFetchTypeGetter
{
    public function __invoke(Node\Expr\ClassConstFetch $node, Scope $scope): Type
    {
        if ($node->name instanceof Node\Identifier && $node->name->toString() === 'class') {
            if ($node->class instanceof Node\Name) {
                if (in_array($node->class->toString(), StaticReference::KEYWORDS)) {
                    return new ConstFetchReferenceType(
                        new StaticReference($node->class->toString()),
                        $node->name->toString(),
                    );
                }

                return new GenericClassStringType(new ObjectType($node->class->toString()));
            }

            $type = $scope->getType($node->class);

            if (
                ($type instanceof ObjectType || $type instanceof NewCallReferenceType)
                && $className = $this->getClassName($type)
            ) {
                return new GenericClassStringType(new ObjectType($className));
            }
        }

        if (
            $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier
        ) {
            $className = in_array($node->class->toString(), StaticReference::KEYWORDS)
                ? new StaticReference($node->class->toString())
                : $node->class->toString();

            return new ConstFetchReferenceType(
                $className,
                $node->name->toString(),
            );
        }

        // In case we're here, it means that we were unable to infer the type from the const fetch. So we rollback to the
        // string type.
        if ($node->name instanceof Node\Identifier && $node->name->toString() === 'class') {
            return new StringType;
        }

        return new UnknownType('Cannot get type from class const fetch');
    }

    private function getClassName(ObjectType|NewCallReferenceType $type): ?string
    {
        if ($type instanceof ObjectType) {
            return $type->name;
        }

        if (is_string($type->name)) {
            return $type->name;
        }

        $typeName = $type->name;

        if ($typeName instanceof LiteralStringType) {
            return $typeName->value;
        }

        if ($typeName instanceof TemplateType && $typeName->is instanceof ObjectType) {
            return $typeName->is->name;
        }

        return null;
    }
}
