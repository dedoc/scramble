<?php

namespace Dedoc\Documentor\Support\ResponseExtractor;

use Dedoc\Documentor\Support\Generator\OpenApi;
use Dedoc\Documentor\Support\Generator\Schema;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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
    private OpenApi $openApi;
    private $getFqName;

    public function __construct(OpenApi $openApi, Array_ $arrayNode, callable $getFqName)
    {
        $this->node = $arrayNode;
        $this->openApi = $openApi;
        $this->getFqName = $getFqName;
    }

    public function __invoke()
    {
        $requiredFields = [];
        $result = collect($this->node->items)
            ->mapWithKeys(function (ArrayItem $arrayItem) use (&$requiredFields) {
                // new JsonResource
                if (
                    $arrayItem->key instanceof String_
                    && $arrayItem->value instanceof Expr\New_
                    && $arrayItem->value->class instanceof Name
                    && is_a($resourceClassName = ($this->getFqName)($arrayItem->value->class->toString()), JsonResource::class, true)
                ) {
                    // if call to `whenLoaded` in constructor, then field is not required
                    $isCallToWhenLoaded = $arrayItem->value->args[0]->value instanceof Expr\MethodCall
                        && $arrayItem->value->args[0]->value->name instanceof Identifier
                        && $arrayItem->value->args[0]->value->name->toString() === 'whenLoaded';

                    if (! $isCallToWhenLoaded) {
                        $requiredFields[] = $arrayItem->key->value;
                    }

                    (new JsonResourceResponseExtractor($this->openApi, $resourceClassName))->extract();

                    return [
                        $arrayItem->key->value => Schema::reference('schemas', class_basename($resourceClassName)),
                    ];
                }

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
                            [$sampleResponse] = (new ArrayNodeSampleInferer($this->openApi, $argValue, $this->getFqName))();

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
                            [$sampleResponse, $requiredArrayFields] = (new ArrayNodeSampleInferer($this->openApi, $argValue, $this->getFqName))();

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
