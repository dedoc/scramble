<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Infer\Infer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\TypeHandlers\TypeHandlers;

/**
 * Transforms PHP type to OpenAPI schema type.
 */
class TypeTransformer
{
    private Infer $infer;

    private Components $components;

    private array $extensions = [];

    public function __construct(Infer $infer, Components $components, array $extensions = [])
    {
        $this->infer = $infer;
        $this->components = $components;
        $this->extensions = $extensions;
    }

    public function transform(Type $type)
    {
        $openApiType = new StringType();

        if (
            $type instanceof \Dedoc\Scramble\Support\Type\ArrayType
            && collect($type->items)->every(fn ($t) => is_numeric($t->key))
        ) {
            $itemsType = isset($type->items[0])
                ? $this->transform($type->items[0]->value)
                : new StringType();

            $openApiType = (new ArrayType())->setItems($itemsType);
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
                    $openApiType = $this->transform($notNullType)->nullable(true);
                } else {
                    $openApiType = new NullType();
                }
            } else {
                $openApiType = (new AnyOf)->setItems(array_map(
                    fn ($t) => $this->transform($t),
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
        } elseif ($typeHandledByExtension = $this->handleUsingExtensions($type)) {
            $openApiType = $typeHandledByExtension;
        }

        return $openApiType;
    }

    private function handleUsingExtensions(Type $type)
    {
        return array_reduce(
            $this->extensions,
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

                if ($type = $extension->toSchema($type, $acc)) {
                    if ($reference) {
                        return $this->components->addSchema($reference->fullName, Schema::fromType($type));
                    }

                    return $type;
                }

                return $acc;
            }
        );
    }
}
