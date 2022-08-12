<?php

namespace Dedoc\Documentor\Support\ResponseExtractor;

use Dedoc\Documentor\Support\Generator\OpenApi;
use Dedoc\Documentor\Support\Generator\Types\ArrayType;
use Dedoc\Documentor\Support\Generator\Types\BooleanType;
use Dedoc\Documentor\Support\Generator\Types\IntegerType;
use Dedoc\Documentor\Support\Generator\Types\NumberType;
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
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
 * - value type from property fetch on resource ($this->id) +
 * - value type from property fetch on resource prop ($this->resource->id) +
 * - value type from resource construction (new SomeResource(xxx)), when xxx is `when` call, key is optional +
 * - arrays within (objects)
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

    private ?Collection $modelInfo;

    public function __construct(OpenApi $openApi, Array_ $arrayNode, callable $getFqName, ?Collection $modelInfo)
    {
        $this->node = $arrayNode;
        $this->openApi = $openApi;
        $this->getFqName = $getFqName;
        $this->modelInfo = $modelInfo;
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

                    $response = (new JsonResourceResponseExtractor($this->openApi, $resourceClassName))->extract();

                    return [
                        $arrayItem->key->value => $response->getContent('application/json'),
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
                            [$sampleResponse] = (new ArrayNodeSampleInferer($this->openApi, $argValue, $this->getFqName, $this->modelInfo))();

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
                            [$sampleResponse, $requiredArrayFields] = (new ArrayNodeSampleInferer($this->openApi, $argValue, $this->getFqName, $this->modelInfo))();

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
        // value type from property fetch on resource ($this->id)
        $isThisPropertyFetch = $value instanceof Expr\PropertyFetch
            && $value->var instanceof Expr\Variable
            && is_string($value->var->name)
            && $value->var->name === 'this';

        // value type from property fetch on resource prop ($this->resource->id)
        $isThisResourcePropertyFetch = $value instanceof Expr\PropertyFetch
            && (
                $value->var instanceof Expr\PropertyFetch
                && $value->var->var instanceof Expr\Variable
                && is_string($value->var->var->name)
                && $value->var->var->name === 'this'
                && $value->var->name instanceof Identifier && $value->var->name->toString() === 'resource'
            );

        if (
            ($isThisPropertyFetch || $isThisResourcePropertyFetch)
            && $value->name instanceof Identifier
        ) {
            $attrType = $this->modelInfo['attributes'][$value->name->toString()] ?? null;

            if ($attrType) {
                $schemaTypes = [
                    'int' => new IntegerType(),
                    'integer' => new IntegerType(),
                    'bigint' => new IntegerType(),
                    'float' => new NumberType(),
                    'double' => new NumberType(),
                    'string' => new StringType(),
                    'datetime' => new StringType(),
                    'bool' => new BooleanType(),
                    'boolean' => new BooleanType(),
                    'array' => new ArrayType(),
                ];

                $type = $schemaTypes[explode(' ', $attrType['type'])[0]] ?? $schemaTypes[$attrType['cast']] ?? new StringType();
                $type->nullable((bool) $attrType['nullable']);

                return $type;
            }
        }

//        if (
//            $value instanceof Expr\PropertyFetch
//            && $value->var instanceof Expr\Variable && is_string($value->var->name) && $value->var->name === 'this'
//            && $attrType = optional($this->modelInfo)->get('attributes')->get()
//        )
        return '';
    }
}
