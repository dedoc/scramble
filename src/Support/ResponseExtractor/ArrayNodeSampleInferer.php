<?php

namespace Dedoc\Documentor\Support\ResponseExtractor;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

/**
 * Simple static analysis is really simple. It works only when array is returned from `toArray` and
 * can understand only few things regarding typing:
 * - key type when string +
 * - value type from property fetch on resource ($this->id)
 * - value type from property fetch on resource prop ($this->resource->id)
 * - value type from resource construction (new SomeResource(xxx)), when xxx is `when` call, key is optional
 * - optionally merged arrays:
 * -- mergeWhen +
 * -- merge +
 * -- when
 */
class ArrayNodeSampleInferer
{
    private Array_ $node;

    public function __construct(Array_ $arrayNode)
    {
        $this->node = $arrayNode;
    }

    public function __invoke()
    {
        $requiredFields = [];
        $result = collect($this->node->items)
            ->mapWithKeys(function (ArrayItem $arrayItem) use (&$requiredFields) {
                if ($arrayItem->key instanceof String_) {
                    $requiredFields[] = $arrayItem->key->value;

                    return [
                        $arrayItem->key->value => $this->getArrayItemValueSample($arrayItem->value),
                    ];
                }

                if ($arrayItem->key === null) {
                    // optionally merged arrays
                    // $this->mergeWhen
                    if (
                        $arrayItem->value instanceof Expr\MethodCall
                        && $arrayItem->value->var instanceof Expr\Variable && $arrayItem->value->var->name === 'this'
                        && $arrayItem->value->name instanceof Identifier && $arrayItem->value->name->toString() === 'mergeWhen'
                        && isset($arrayItem->value->args[1])
                    ) {
                        $argValue = $arrayItem->value->args[1]->value;

                        if ($argValue instanceof Expr\Closure) {
                            $argValue = $argValue->stmts[count($argValue->stmts) - 1]->expr ?? null;
                        }

                        if ($argValue instanceof Array_) {
                            [$sampleResponse] = (new ArrayNodeSampleInferer($argValue))();

                            return $sampleResponse;
                        }
                    }

                    // $this->merge
                    if (
                        $arrayItem->value instanceof Expr\MethodCall
                        && $arrayItem->value->var instanceof Expr\Variable && $arrayItem->value->var->name === 'this'
                        && $arrayItem->value->name instanceof Identifier && $arrayItem->value->name->toString() === 'merge'
                        && isset($arrayItem->value->args[0])
                    ) {
                        $argValue = $arrayItem->value->args[0]->value;

                        if ($argValue instanceof Expr\Closure) {
                            $argValue = $argValue->stmts[count($argValue->stmts) - 1]->expr ?? null;
                        }

                        if ($argValue instanceof Array_) {
                            [$sampleResponse, $requiredArrayFields] = (new ArrayNodeSampleInferer($argValue))();

                            $requiredFields = array_merge($requiredFields, $requiredArrayFields);

                            return $sampleResponse;
                        }
                    }
                }

                return [];
            })
            ->toArray();

        return [$result, $requiredFields];
    }

    private function getArrayItemValueSample(Expr $value)
    {
        return '';
    }
}
