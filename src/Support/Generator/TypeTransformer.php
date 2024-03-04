<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * Transforms PHP type to OpenAPI schema type.
 */
class TypeTransformer
{
    private Infer $infer;

    private Components $components;

    private array $typeToSchemaExtensions;

    private array $exceptionToResponseExtensions;

    public function __construct(
        Infer $infer,
        Components $components,
        array $typeToSchemaExtensions = [],
        array $exceptionToResponseExtensions = []
    ) {
        $this->infer = $infer;
        $this->components = $components;
        $this->typeToSchemaExtensions = $typeToSchemaExtensions;
        $this->exceptionToResponseExtensions = $exceptionToResponseExtensions;
    }

    public function getComponents(): Components
    {
        return $this->components;
    }

    public function transform(Type $type)
    {
        $openApiType = new StringType();

        if (
            $type instanceof \Dedoc\Scramble\Support\Type\ArrayType
            && (
                (collect($type->items)->every(fn ($t) => is_numeric($t->key)) && collect($type->items)->count() === 1)
                || collect($type->items)->every(fn ($t) => $t->key === null)
            )
        ) {
            $isMap = collect($type->items)->every(fn ($t) => $t->key === null)
                && count($type->items) === 2;

            if ($isMap) {
                $keyType = $this->transform($type->items[0]->value);

                if ($keyType instanceof IntegerType) {
                    $openApiType = (new ArrayType)
                        ->setItems($this->transform($type->items[1]->value));
                } else {
                    $openApiType = (new ObjectType)
                        ->additionalProperties($this->transform($type->items[1]->value));
                }
            } else {
                $itemsType = isset($type->items[0])
                    ? $this->transform($type->items[0]->value)
                    : new StringType();

                $openApiType = (new ArrayType())->setItems($itemsType);
            }
        } elseif (
            $type instanceof \Dedoc\Scramble\Support\Type\ArrayType
        ) {
            $openApiType = new ObjectType();
            $requiredKeys = [];

            $props = collect($type->items)
                ->mapWithKeys(function (ArrayItemType_ $item) use (&$requiredKeys) {
                    if (! $item->isOptional) {
                        $requiredKeys[] = $item->key;
                    }

                    return [
                        $item->key => $this->transform($item),
                    ];
                });

            $openApiType->properties = $props->all();

            $openApiType->setRequired($requiredKeys);
        } elseif ($type instanceof ArrayItemType_) {
            $openApiType = $this->transform($type->value);

            if ($docNode = $type->getAttribute('docNode')) {
                $varNode = $docNode->getVarTagValues()[0] ?? null;

                $openApiType = $varNode && $varNode->type
                    ? $this->transform(PhpDocTypeHelper::toType($varNode->type))
                    : $openApiType;

                if ($varNode && $varNode->description) {
                    $openApiType->setDescription($varNode->description);
                }
            }
        } elseif ($type instanceof Union) {
            if (count($type->types) === 2 && collect($type->types)->contains(fn ($t) => $t instanceof \Dedoc\Scramble\Support\Type\NullType)) {
                $notNullType = collect($type->types)->first(fn ($t) => ! ($t instanceof \Dedoc\Scramble\Support\Type\NullType));
                if ($notNullType) {
                    $openApiType = $this->transform($notNullType)->nullable(true);
                } else {
                    $openApiType = new NullType();
                }
            } else {
                [$literals, $otherTypes] = collect($type->types)
                    ->partition(fn ($t) => $t instanceof LiteralStringType || $t instanceof LiteralIntegerType);

                [$stringLiterals, $integerLiterals] = collect($literals)
                    ->partition(fn ($t) => $t instanceof LiteralStringType);

                $items = array_map($this->transform(...), $otherTypes->values()->toArray());

                if ($stringLiterals->count()) {
                    $items[] = (new StringType())->enum(
                        $stringLiterals->map->value->unique()->toArray()
                    );
                }

                if ($integerLiterals->count()) {
                    $items[] = (new IntegerType())->enum(
                        $integerLiterals->map->value->unique()->toArray()
                    );
                }

                $openApiType = count($items) === 1 ? $items[0] : (new AnyOf)->setItems($items);
            }
        } elseif ($type instanceof LiteralStringType) {
            $openApiType = (new StringType())->example($type->value);
        } elseif ($type instanceof LiteralIntegerType) {
            $openApiType = (new IntegerType())->example($type->value);
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
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\ObjectType) {
            $openApiType = new ObjectType();
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\IntersectionType) {
            $openApiType = (new AllOf)->setItems(array_filter(array_map(
                fn ($t) => $this->transform($t),
                $type->types,
            )));
        }

        if ($typeHandledByExtension = $this->handleUsingExtensions($type)) {
            $openApiType = $typeHandledByExtension;
        }

        if ($type->hasAttribute('format')) {
            $openApiType->format($type->getAttribute('format'));
        }

        return $openApiType;
    }

    private function handleUsingExtensions(Type $type)
    {
        return array_reduce(
            $this->typeToSchemaExtensions,
            function ($acc, $extensionClass) use ($type) {
                $extension = new $extensionClass($this->infer, $this, $this->components);

                if (! $extension->shouldHandle($type)) {
                    return $acc;
                }

                /** @var Reference|null $reference */
                $reference = method_exists($extension, 'reference')
                    ? $extension->reference($type)
                    : null;

                if ($reference && $this->components->hasSchema($reference->fullName)) {
                    return $reference;
                }

                if ($reference) {
                    $this->components->addSchema($reference->fullName, Schema::fromType(new UnknownType('Reference is being analyzed.')));
                }

                if ($handledType = $extension->toSchema($type, $acc)) {
                    if ($reference) {
                        return $this->components->addSchema($reference->fullName, Schema::fromType($handledType));
                    }

                    return $handledType;
                }

                /*
                 * If we couldn't handle a type, the reference is removed.
                 */
                if ($reference) {
                    $this->components->removeSchema($reference->fullName);
                }

                return $acc;
            }
        );
    }

    public function toResponse(Type $type)
    {
        if (! $response = $this->handleResponseUsingExtensions($type)) {
            if ($type->isInstanceOf(\Throwable::class)) {
                return null;
            }

            $response = Response::make(200)
                ->setContent(
                    'application/json',
                    Schema::fromType($this->transform($type))
                );
        }

        /** @var PhpDocNode $docNode */
        if ($docNode = $type->getAttribute('docNode')) {
            $description = (string) Str::of($docNode->getAttribute('summary') ?: '')
                ->append("\n\n".($docNode->getAttribute('description') ?: ''))
                ->append("\n\n".$response->description)
                ->trim();
            $response->description($description);

            $code = (int) (array_values($docNode->getTagsByName('@status'))[0]->value->value ?? 200);
            $response->code = $code;

            if ($varType = $docNode->getVarTagValues()[0]->type ?? null) {
                $response->setContent(
                    'application/json',
                    Schema::fromType($this->transform(
                        PhpDocTypeHelper::toType($varType),
                    ))
                );
            }
        }

        return $response;
    }

    private function handleResponseUsingExtensions(Type $type)
    {
        if (! $type->isInstanceOf(\Throwable::class)) {
            return array_reduce(
                $this->typeToSchemaExtensions,
                function ($acc, $extensionClass) use ($type) {
                    $extension = new $extensionClass($this->infer, $this, $this->components);

                    if (! $extension->shouldHandle($type)) {
                        return $acc;
                    }

                    if ($response = $extension->toResponse($type, $acc)) {
                        return $response;
                    }

                    return $acc;
                }
            );
        }

        return array_reduce(
            $this->exceptionToResponseExtensions,
            function ($acc, $extensionClass) use ($type) {
                $extension = new $extensionClass($this->infer, $this, $this->components);

                if (! $extension->shouldHandle($type)) {
                    return $acc;
                }

                /** @var Reference|null $reference */
                $reference = method_exists($extension, 'reference')
                    ? $extension->reference($type)
                    : null;

                if ($reference && $this->components->has($reference)) {
                    return $reference;
                }

                if ($response = $extension->toResponse($type, $acc)) {
                    if ($reference) {
                        return $this->components->add($reference, $response);
                    }

                    return $response;
                }

                return $acc;
            }
        );
    }
}
