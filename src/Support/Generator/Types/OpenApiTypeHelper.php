<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeHandlers\TypeHandlers;

class OpenApiTypeHelper
{
    public static function fromType(\Dedoc\Scramble\Support\Type\AbstractType $type): Type
    {
        $openApiType = new StringType();

        if (
            $type instanceof \Dedoc\Scramble\Support\Type\ArrayType
            && collect($type->items)->every(fn ($t) => is_numeric($t->key))
        ) {
            $itemsType = isset($type->items[0])
                ? static::fromType($type->items[0]->value)
                : new StringType();

            $openApiType = (new ArrayType())->setItems($itemsType);
        } elseif (
            $type instanceof \Dedoc\Scramble\Support\Type\ArrayType
        ) {
            $openApiType = new ObjectType();
            $requiredKeys = [];

            $props = collect($type->items)
                ->mapWithKeys(function (ArrayItemType_ $item) use (&$requiredKeys) {
                    if (! $item->key) {
                        if ($item->value instanceof UnknownType) {
                            return [];
                        }
                        if (
                            $item->value instanceof Generic
                            && $item->value->type->name === 'Illuminate\Http\Resources\MergeValue'
                        ) {
                            $nestedType = static::fromType($item->value->genericTypes[1]);

                            if (! ($nestedType instanceof ObjectType)) {
                                return [];
                            }

                            if (
                                $item->value->genericTypes[0] instanceof LiteralBooleanType
                                && $item->value->genericTypes[0]->value === true
                            ) {
                                $requiredKeys = array_merge($requiredKeys, $nestedType->required);
                            }

                            return $nestedType->properties;
                        }
                    }
                    if (! $item->isOptional) {
                        $requiredKeys[] = $item->key;
                    }

                    return [
                        $item->key => static::fromType($item),
                    ];
                });

            $openApiType->properties = $props->all();

            $openApiType->setRequired($requiredKeys);
        } elseif ($type instanceof ArrayItemType_) {
            $openApiType = static::fromType($type->value);

            if ($docNode = $type->getAttribute('docNode')) {
                $varNode = $docNode->getVarTagValues()[0] ?? null;

                // @todo: unknown type
                $openApiType = $varNode->type
                    ? (TypeHandlers::handle($varNode->type) ?: new StringType)
                    : new StringType;

                if ($varNode->description) {
                    $openApiType->setDescription($varNode->description);
                }
            }
        } elseif ($type instanceof Union) {
            if (count($type->types) === 2 && collect($type->types)->contains(fn ($t) => $t instanceof \Dedoc\Scramble\Support\Type\NullType)) {
                $notNullType = collect($type->types)->first(fn ($t) => ! ($t instanceof \Dedoc\Scramble\Support\Type\NullType));
                if ($notNullType) {
                    $openApiType = static::fromType($notNullType)->nullable(true);
                } else {
                    $openApiType = new NullType();
                }
            } else {
                $openApiType = (new AnyOf)->setItems(array_map(
                    fn ($t) => static::fromType($t),
                    $type->types,
                ));
            }
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\StringType) {
            $openApiType = new StringType();
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\FloatType) {
            $openApiType = new NumberType();
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\IntegerType) {
            $openApiType = new IntegerType();
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\BooleanType) {
            $openApiType = new BooleanType();
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\NullType) {
            $openApiType = new NullType();
        } elseif ($complexTypeHandled = ComplexTypeHandlers::handle($type)) {
            $openApiType = $complexTypeHandled;
        }

        return $openApiType;
    }
}
