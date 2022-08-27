<?php

namespace Dedoc\Scramble\Support\ComplexTypeHandler;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Identifier as TypeIdentifier;
use Dedoc\Scramble\Support\TypeHandlers\TypeHandlers;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

class JsonResourceToArrayNodeTypeGetter
{
    private Array_ $node;

    private $namesResolver;

    private ?Collection $modelInfo;

    public function __construct(Array_ $node, callable $namesResolver, ?Collection $modelInfo)
    {
        $this->node = $node;
        $this->namesResolver = $namesResolver;
        $this->modelInfo = $modelInfo;
    }

    public function __invoke(): ObjectType
    {
        $requiredFields = [];
        $type = new ObjectType;

        $fields = collect($this->node->items)
            ->mapWithKeys(function (ArrayItem $arrayItem) use (&$requiredFields) {
                // new JsonResource
                if (
                    $arrayItem->key instanceof String_
                    && $arrayItem->value instanceof Expr\New_
                    && $arrayItem->value->class instanceof Name
                    && is_a($resourceClassName = ($this->namesResolver)($arrayItem->value->class->toString()), JsonResource::class, true)
                    && ! is_a($resourceClassName, ResourceCollection::class, true)
                ) {
                    // if call to `whenLoaded` in constructor, then field is not required
                    $isCallToWhenLoaded = $arrayItem->value->args[0]->value instanceof Expr\MethodCall
                        && $arrayItem->value->args[0]->value->name instanceof Identifier
                        && $arrayItem->value->args[0]->value->name->toString() === 'whenLoaded';

                    if (! $isCallToWhenLoaded) {
                        $requiredFields[] = $arrayItem->key->value;
                    }

                    return [
                        $arrayItem->key->value => ($type = ComplexTypeHandlers::handle(new TypeIdentifier($resourceClassName)))
                            ? $type
                            : new StringType,
                    ];
                }

                // array
                if (
                    $arrayItem->key instanceof String_
                    && $arrayItem->value instanceof Expr\Array_
                ) {
                    $requiredFields[] = $arrayItem->key->value;

                    return [
                        $arrayItem->key->value => (new static($arrayItem->value, $this->namesResolver, $this->modelInfo))(),
                    ];
                }

                // call to when
                if (
                    $arrayItem->key instanceof String_
                    && $arrayItem->value instanceof Expr\MethodCall
                    && $arrayItem->value->var instanceof Expr\Variable && $arrayItem->value->var->name === 'this'
                    && $arrayItem->value->name instanceof Identifier && $arrayItem->value->name->toString() === 'when'
                    && isset($arrayItem->value->args[1])
                ) {
                    $argValue = $arrayItem->value->args[1]->value;
                    if ($argValue instanceof Expr\Closure) {
                        $argValue = $argValue->stmts[count($argValue->stmts) - 1]->expr ?? null;
                    }
                    if ($argValue instanceof Expr\ArrowFunction) {
                        $argValue = $argValue->expr;
                    }

                    $syntheticArrayItem = new ArrayItem($argValue, new String_('check'));
                    if ($doc = $arrayItem->getDocComment()) {
                        $syntheticArrayItem->setDocComment($doc);
                    }
                    $syntheticArray = new Array_([$syntheticArrayItem]);

                    $type = (new static($syntheticArray, $this->namesResolver, $this->modelInfo))();

                    return [
                        $arrayItem->key->value => $type->getProperty('check'),
                    ];
                }

                if ($arrayItem->key instanceof String_ && $doc = $arrayItem->getDocComment()) {
                    $requiredFields[] = $arrayItem->key->value;

                    $docNode = PhpDoc::parse($doc->getText());
                    $varNode = $docNode->getVarTagValues()[0] ?? null;

                    // @todo: unknown type
                    $type = $varNode->type
                        ? (TypeHandlers::handle($varNode->type) ?: new StringType)
                        : new StringType;

                    if ($varNode->description) {
                        $type->setDescription($varNode->description);
                    }

                    return [$arrayItem->key->value => $type];
                }

                if ($arrayItem->key instanceof String_) {
                    $requiredFields[] = $arrayItem->key->value;

                    return [
                        $arrayItem->key->value => $this->getArrayItemValueType($arrayItem->value),
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
                        if ($argValue instanceof Expr\ArrowFunction) {
                            $argValue = $argValue->expr;
                        }

                        if ($argValue instanceof Array_) {
                            return (new self($argValue, $this->namesResolver, $this->modelInfo))()->properties;
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
                        if ($argValue instanceof Expr\ArrowFunction) {
                            $argValue = $argValue->expr;
                        }

                        if ($argValue instanceof Array_) {
                            $type = (new self($argValue, $this->namesResolver, $this->modelInfo))();

                            $requiredFields = array_merge($requiredFields, $type->required);

                            return $type->properties;
                        }
                    }
                }

                return [];
            })
            ->toArray();

        foreach ($fields as $name => $fieldType) {
            $type->addProperty($name, $fieldType);
        }

        return $type->setRequired($requiredFields);
    }

    private function getArrayItemValueType(Expr $value)
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

        return new StringType;
    }
}
