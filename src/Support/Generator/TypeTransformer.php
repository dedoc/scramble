<?php

namespace Dedoc\Scramble\Support\Generator;

use Carbon\CarbonInterface;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

use function DeepCopy\deep_copy;

/**
 * Transforms PHP type to OpenAPI schema type.
 */
class TypeTransformer
{
    /** @var TypeToSchemaExtension[] */
    private array $typeToSchemaExtensions;

    /** @var ExceptionToResponseExtension[] */
    private array $exceptionToResponseExtensions;

    public function __construct(
        private Infer $infer,
        public readonly OpenApiContext $context,
        array $typeToSchemaExtensionsClasses = [],
        array $exceptionToResponseExtensionsClasses = []
    ) {
        $this->typeToSchemaExtensions = collect($typeToSchemaExtensionsClasses)
            ->map(function ($extensionClass) {
                return new $extensionClass($this->infer, $this, $this->getComponents(), $this->context);
            })
            ->all();

        $this->exceptionToResponseExtensions = collect($exceptionToResponseExtensionsClasses)
            ->map(function ($extensionClass) {
                return new $extensionClass($this->infer, $this, $this->getComponents(), $this->context);
            })
            ->all();
    }

    public function getComponents(): Components
    {
        return $this->context->openApi->components;
    }

    public function transform(Type $type): OpenApiType
    {
        $openApiType = new UnknownType;

        if ($type instanceof TemplateType && $type->is) {
            $type = $type->is;
        }

        if (
            $type instanceof \Dedoc\Scramble\Support\Type\KeyedArrayType
            && $type->isList
        ) {
            /** @see https://stackoverflow.com/questions/57464633/how-to-define-a-json-array-with-concrete-item-definition-for-every-index-i-e-a */
            $openApiType = (new ArrayType)
                ->setMin(count($type->items))
                ->setMax(count($type->items))
                ->setPrefixItems(
                    array_map(
                        fn ($item) => $this->transform($item->value),
                        $type->items
                    )
                )
                ->setAdditionalItems(false);
        } elseif (
            $type instanceof \Dedoc\Scramble\Support\Type\KeyedArrayType
            && ! $type->isList
        ) {
            $openApiType = new ObjectType;
            $requiredKeys = [];

            $props = collect($type->items)
                ->mapWithKeys(function (ArrayItemType_ $item) use (&$requiredKeys) {
                    if (! $item->isOptional) {
                        $requiredKeys[] = $item->key;
                    }

                    return [
                        (string) $item->key => $this->transform($item),
                    ];
                });

            $openApiType->properties = $props->all();

            $openApiType->setRequired($requiredKeys);
        } elseif (
            $type instanceof \Dedoc\Scramble\Support\Type\ArrayType
        ) {
            $keyType = $this->transform($type->key);

            if ($keyType instanceof IntegerType) {
                $openApiType = (new ArrayType)->setItems($this->transform($type->value));
            } else {
                $openApiType = (new ObjectType)
                    ->additionalProperties($this->transform($type->value));
            }
        } elseif ($type instanceof ArrayItemType_) {
            $openApiType = $this->transform($type->value);

            /** @var PhpDocNode|null $valueDocNode */
            $valueDocNode = $type->value->getAttribute('docNode');
            /** @var PhpDocNode|null $arrayItemDocNode */
            $arrayItemDocNode = $type->getAttribute('docNode');

            if ($valueDocNode || $arrayItemDocNode) {
                $docNode = new PhpDocNode([
                    ...($arrayItemDocNode?->children ?: []),
                    ...($valueDocNode?->children ?: []),
                ]);
                PhpDoc::addSummaryAttributes($docNode);

                /** @var PhpDocNode $docNode */
                $varNode = array_values($docNode->getVarTagValues())[0] ?? null;

                $openApiType = $varNode
                    ? $this->transform(PhpDocTypeHelper::toType($varNode->type))
                    : $openApiType;

                $commentDescription = trim($docNode->getAttribute('summary').' '.$docNode->getAttribute('description'));
                $varNodeDescription = $varNode && $varNode->description ? trim($varNode->description) : '';
                if ($commentDescription || $varNodeDescription) {
                    $openApiType->setDescription(implode('. ', array_filter([$varNodeDescription, $commentDescription])));
                }

                if ($examples = ExamplesExtractor::make($docNode)->extract(preferString: $openApiType instanceof StringType)) {
                    $openApiType->examples($examples);
                }

                if ($default = ExamplesExtractor::make($docNode, '@default')->extract(preferString: $openApiType instanceof StringType)) {
                    $openApiType->default($default[0]);
                }

                if ($format = array_values($docNode->getTagsByName('@format'))[0]->value->value ?? null) {
                    $openApiType->format($format);
                }
            }
        } elseif ($type instanceof Union) {
            if (count($type->types) === 2 && collect($type->types)->contains(fn ($t) => $t instanceof \Dedoc\Scramble\Support\Type\NullType)) {
                $notNullType = collect($type->types)->first(fn ($t) => ! ($t instanceof \Dedoc\Scramble\Support\Type\NullType));
                if ($notNullType) {
                    $openApiType = $this->transform($notNullType)->nullable(true);
                } else {
                    $openApiType = new NullType;
                }
            } else {
                [$literals, $otherTypes] = collect($type->types)
                    ->partition(fn ($t) => $t instanceof LiteralStringType || $t instanceof LiteralIntegerType)
                    ->all();

                [$stringLiterals, $integerLiterals] = collect($literals)
                    ->partition(fn ($t) => $t instanceof LiteralStringType)
                    ->all();

                $items = array_map($this->transform(...), $otherTypes->values()->toArray()); // @phpstan-ignore argument.type

                if ($stringLiterals->count()) {
                    $items[] = (new StringType)->enum(
                        $stringLiterals->map->value->unique()->values()->toArray() // @phpstan-ignore property.notFound
                    );
                }

                if ($integerLiterals->count()) {
                    $items[] = (new IntegerType)->enum(
                        $integerLiterals->map->value->unique()->values()->toArray() // @phpstan-ignore property.notFound
                    );
                }

                // Removing duplicated schemas before making a resulting AnyOf type.
                $uniqueItems = collect($items)->unique(fn ($i) => json_encode($i->toArray()))->values()->all();
                $openApiType = count($uniqueItems) === 1 ? $uniqueItems[0] : (new AnyOf)->setItems($uniqueItems);
            }
        } elseif ($type instanceof LiteralStringType) {
            $openApiType = (new StringType)->enum([$type->value]);
        } elseif ($type instanceof LiteralIntegerType) {
            $openApiType = (new IntegerType)->enum([$type->value]);
        } elseif ($type instanceof LiteralFloatType) {
            $openApiType = (new NumberType)->enum([$type->value]);
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\StringType) {
            $openApiType = new StringType;
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\FloatType) {
            $openApiType = new NumberType;
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\IntegerRangeType) {
            $openApiType = new IntegerType;

            if ($type->min !== null) {
                $openApiType->setMin($type->min);
            }

            if ($type->max !== null) {
                $openApiType->setMax($type->max);
            }
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\IntegerType) {
            $openApiType = new IntegerType;
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\BooleanType) {
            $openApiType = new BooleanType;
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\NullType) {
            $openApiType = new NullType;
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\MixedType) {
            $openApiType = new MixedType;
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\ObjectType) {
            if ($type->isInstanceOf(CarbonInterface::class)) {
                $openApiType = (new StringType)->format('date-time');
            } else {
                $openApiType = new ObjectType;
            }
        } elseif ($type instanceof \Dedoc\Scramble\Support\Type\IntersectionType) {
            $openApiType = (new AllOf)->setItems(array_map(
                fn ($t) => $this->transform($t),
                $type->types,
            ));
        }

        if ($typeHandledByExtension = $this->handleUsingExtensions($type)) {
            $openApiType = $typeHandledByExtension;
        }

        if ($type->hasAttribute('format')) {
            $openApiType->format($type->getAttribute('format'));
        }

        if ($type->hasAttribute('file')) {
            $openApiType->setAttribute('file', $type->getAttribute('file'));
        }

        if ($type->hasAttribute('line')) {
            $openApiType->setAttribute('line', $type->getAttribute('line'));
        }

        return $openApiType;
    }

    private function handleUsingExtensions(Type $type)
    {
        /** @var Collection $extensions */
        $extensions = collect($this->typeToSchemaExtensions)
            ->filter->shouldHandle($type)
            ->values();

        $referenceExtension = $extensions->last();

        /** @var Reference|null $reference */
        $reference = $referenceExtension && method_exists($referenceExtension, 'reference')
            ? $referenceExtension->reference($type)
            : null;

        if ($reference && $this->context->references->schemas->has($reference->fullName)) {
            return $this->context->references->schemas->add($reference->fullName, $reference);
        }

        if ($reference) {
            $reference = $this->context->references->schemas->add($reference->fullName, $reference);

            $this->getComponents()->addSchema($reference->fullName, Schema::fromType(new UnknownType('Reference is being analyzed.')));
        }

        $handledType = $extensions
            ->reduce(function ($acc, $extension) use ($type) {
                return $extension->toSchema($type, $acc) ?: $acc;
            });

        if ($handledType && $reference) {
            $this->getComponents()->addSchema($reference->fullName, Schema::fromType($handledType));
        }

        /*
        * If we couldn't handle a type, the reference is removed.
        */
        if (! $handledType && $reference) {
            $this->getComponents()->removeSchema($reference->fullName);
        }

        return $reference ?: $handledType;
    }

    public function toResponse(Type $type): Response|Reference|null
    {
        // In case of union type being returned and all of its types resulting in the same response, we want to make
        // sure to take only unique types to avoid having the same types in the response.
        if ($type instanceof Union) {
            $uniqueItems = collect($type->types)->unique(fn ($i) => json_encode($this->transform($i)->toArray()))->values()->all();
            $type = count($uniqueItems) === 1 ? $uniqueItems[0] : Union::wrap($uniqueItems);
        }

        if (! $response = $this->handleResponseUsingExtensions($type)) {
            if ($type->isInstanceOf(\Throwable::class)) {
                return null;
            }

            $response = Response::make(200)
                ->setContent(
                    'application/json',
                    Schema::fromType($this->transform($type)),
                );
        }

        /** @var PhpDocNode $docNode */
        if ($docNode = $type->getAttribute('docNode')) {
            $description = (string) Str::of($docNode->getAttribute('summary') ?: '')
                ->append("\n\n".($docNode->getAttribute('description') ?: ''))
                ->append("\n\n".$response->description)
                ->trim();
            $response->description($description);

            $code = (int) (array_values($docNode->getTagsByName('@status'))[0]->value->value ?? $response->code ?? 200);
            $response->code = $code;

            if ($varType = $docNode->getVarTagValues()[0]->type ?? null) {
                $type = PhpDocTypeHelper::toType($varType);

                $typeResponse = $this->toResponse($type);

                if ($typeResponse instanceof Reference) {
                    $typeResponse = deep_copy($typeResponse->resolve());
                }

                if ($typeResponse instanceof Response) {
                    $response->setContent('application/json', $typeResponse->getContent('application/json'));
                }
            }
        }

        return $response;
    }

    private function handleResponseUsingExtensions(Type $type)
    {
        if (! $type->isInstanceOf(\Throwable::class)) {
            foreach (array_reverse($this->typeToSchemaExtensions) as $extension) {
                if (! $extension->shouldHandle($type)) {
                    continue;
                }

                if ($response = $extension->toResponse($type, null)) {
                    return $response;
                }
            }
        }

        // We want latter registered extensions to have a higher priority to allow custom extensions to override default ones.
        $priorityExtensions = array_reverse($this->exceptionToResponseExtensions);

        return array_reduce(
            $priorityExtensions,
            function ($acc, $extension) use ($type) {
                if (! $extension->shouldHandle($type)) {
                    return $acc;
                }

                /** @var Reference|null $reference */
                $reference = method_exists($extension, 'reference')
                    ? $extension->reference($type)
                    : null;

                if ($reference && $this->getComponents()->has($reference)) {
                    return $reference;
                }

                if ($response = $extension->toResponse($type, $acc)) {
                    if ($reference) {
                        return $this->getComponents()->add($reference, $response);
                    }

                    return $response;
                }

                return $acc;
            }
        );
    }
}
