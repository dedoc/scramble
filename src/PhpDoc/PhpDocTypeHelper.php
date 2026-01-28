<?php

namespace Dedoc\Scramble\PhpDoc;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\IntegerRangeType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\IntersectionType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeParameterNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class PhpDocTypeHelper
{
    public static function toType(TypeNode $type)
    {
        if ($type instanceof GenericTypeNode && $type->type->name === 'int') {
            return self::handleGenericInteger($type->genericTypes);
        }

        if ($type instanceof IdentifierTypeNode) {
            return self::handleIdentifierNode($type);
        }

        if ($type instanceof ArrayShapeNode) {
            return new KeyedArrayType(array_map(
                function (ArrayShapeItemNode $t) {
                    $keyName = $t->keyName instanceof IdentifierTypeNode
                        ? $t->keyName->name
                        : $t->keyName->value ?? null;

                    return new ArrayItemType_(
                        $keyName,
                        static::toType($t->valueType),
                        $t->optional,
                    );
                },
                $type->items,
            ));
        }

        if ($type instanceof ArrayTypeNode) {
            return new ArrayType(
                value: static::toType($type->type),
            );
        }

        if ($type instanceof GenericTypeNode) {
            if ($type->type->name === 'array') {
                if (count($type->genericTypes) === 1) {
                    return new ArrayType(
                        value: static::toType($type->genericTypes[0]),
                    );
                }

                return new ArrayType(
                    value: static::toType($type->genericTypes[1]),
                    key: static::toType($type->genericTypes[0]),
                );
            }

            if ($type->type->name === 'class-string') {
                return new GenericClassStringType(static::toType($type->genericTypes[0]));
            }

            if ($type->type->name === 'list') {
                $valueType = isset($type->genericTypes[0]) ? static::toType($type->genericTypes[0]) : new MixedType;

                return new ArrayType(value: $valueType);
            }

            if (! ($typeObject = static::toType($type->type)) instanceof ObjectType) {
                return $typeObject;
            }

            return new Generic(
                $typeObject->name,
                array_map(
                    fn ($type) => static::toType($type),
                    $type->genericTypes,
                )
            );
        }

        if ($type instanceof IntersectionTypeNode) {
            return new IntersectionType(array_map(
                fn ($t) => static::toType($t),
                $type->types,
            ));
        }

        if ($type instanceof ThisTypeNode) {
            return new SelfType(''/** ??? */);
        }

        if ($type instanceof UnionTypeNode) {
            return new Union(array_map(
                fn ($t) => static::toType($t),
                $type->types,
            ));
        }

        if ($type instanceof ConstTypeNode) {
            if ($type->constExpr instanceof ConstExprStringNode) {
                return new LiteralStringType($type->constExpr->value);
            }

            if ($type->constExpr instanceof ConstExprIntegerNode) {
                return new LiteralIntegerType($type->constExpr->value);
            }

            if ($type->constExpr instanceof ConstExprFloatNode) {
                return new FloatType; // todo: float literal?
            }
        }

        if ($type instanceof CallableTypeNode) {
            return new FunctionType(
                name: '{closure}',
                arguments: array_map(
                    fn (CallableTypeParameterNode $n) => self::toType($n->type),
                    $type->parameters,
                ),
                returnType: self::toType($type->returnType),
            );
        }

        return new UnknownType('Unknown phpDoc type ['.$type.']');
    }

    private static function handleIdentifierNode(IdentifierTypeNode $type)
    {
        if ($type->name === 'string') {
            return new StringType;
        }
        if (in_array($type->name, ['float', 'double'])) {
            return new FloatType;
        }
        if (in_array($type->name, [
            'int',
            'integer',
            'positive-int',
            'negative-int',
            'non-positive-int',
            'non-negative-int',
            'non-zero-int',
        ])) {
            return match ($type->name) {
                'int', 'integer', 'non-zero-int' => new IntegerType,
                'positive-int' => new IntegerRangeType(min: 1),
                'negative-int' => new IntegerRangeType(max: -1),
                'non-positive-int' => new IntegerRangeType(max: 0),
                'non-negative-int' => new IntegerRangeType(min: 0),
            };
        }
        if (in_array($type->name, ['bool', 'boolean'])) {
            return new BooleanType;
        }
        if ($type->name === 'true') {
            return new LiteralBooleanType(true);
        }
        if ($type->name === 'false') {
            return new LiteralBooleanType(false);
        }
        if ($type->name === 'scalar') {
            // @todo: Scalar variables are those containing an int, float, string or bool.
            return new StringType;
        }
        if ($type->name === 'array') {
            return new ArrayType;
        }
        if ($type->name === 'array-key') {
            return new Union([
                new IntegerType,
                new StringType,
            ]);
        }
        if ($type->name === 'list') {
            return new ArrayType;
        }
        if ($type->name === 'object') {
            return new ObjectType('\stdClass');
        }
        if ($type->name === 'null') {
            return new NullType;
        }
        if ($type->name === 'mixed') {
            return new MixedType;
        }

        return new ObjectType($type->name);
    }

    /**
     * @param  TypeNode[]  $genericTypes
     */
    private static function handleGenericInteger(array $genericTypes): IntegerType
    {
        if (count($genericTypes) !== 2) {
            return new IntegerType;
        }

        if (! ($genericTypes[0] instanceof ConstTypeNode || $genericTypes[0] instanceof IdentifierTypeNode)) {
            return new IntegerType;
        }

        if ($genericTypes[0] instanceof ConstTypeNode && ! $genericTypes[0]->constExpr instanceof ConstExprIntegerNode) {
            return new IntegerType;
        }

        if ($genericTypes[0] instanceof IdentifierTypeNode && $genericTypes[0]->name !== 'min') {
            return new IntegerType;
        }

        if (! ($genericTypes[1] instanceof ConstTypeNode || $genericTypes[1] instanceof IdentifierTypeNode)) {
            return new IntegerType;
        }

        if ($genericTypes[1] instanceof ConstTypeNode && ! $genericTypes[1]->constExpr instanceof ConstExprIntegerNode) {
            return new IntegerType;
        }

        if ($genericTypes[1] instanceof IdentifierTypeNode && $genericTypes[1]->name !== 'max') {
            return new IntegerType;
        }

        $min = match (true) {
            $genericTypes[0] instanceof ConstTypeNode => $genericTypes[0]->constExpr->value, // @phpstan-ignore property.notFound
            $genericTypes[0] instanceof IdentifierTypeNode => null,
        };

        $max = match (true) {
            $genericTypes[1] instanceof ConstTypeNode => $genericTypes[1]->constExpr->value, // @phpstan-ignore property.notFound
            $genericTypes[1] instanceof IdentifierTypeNode => null,
        };

        return new IntegerRangeType(
            min: $min,
            max: $max,
        );
    }
}
