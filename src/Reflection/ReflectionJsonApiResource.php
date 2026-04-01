<?php

namespace Dedoc\Scramble\Reflection;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\FlattensMergeValues;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class ReflectionJsonApiResource
{
    use FlattensMergeValues;

    private function __construct(public readonly string $name, public readonly ClassDefinition $definition) {}

    public static function createForClass(string $class): self
    {
        return new self($class, app(Infer::class)->analyzeClass($class));
    }

    public function getAttributesType(): ?InferType\KeyedArrayType
    {
        $toAttributesReturnType = $this->getMethodReturnType('toAttributes');
        if ($toAttributesReturnType instanceof InferType\KeyedArrayType) {
            return $toAttributesReturnType;
        }

        $propertiesAttributesType = $this->normalizeAttributesPropertyDefault(
            $this->definition->getPropertyDefinition('attributes')?->defaultType,
        );
        if ($propertiesAttributesType instanceof InferType\KeyedArrayType) {
            return $propertiesAttributesType;
        }

        return null;
    }

//    public function getRelationshipsType(): ?KeyedArrayType
//    {
//        $propertiesRelationshipsType = $this->normalizeRelationshipsPropertyDefault(
//            $this->definition->getPropertyDefinition('relationships')?->defaultType,
//        );
//        $toRelationshipsReturnType = $this->getMethodReturnType('toRelationships');
//
//        if (
//            ! $propertiesRelationshipsType instanceof InferType\KeyedArrayType
//            && ! $toRelationshipsReturnType instanceof InferType\KeyedArrayType
//        ) {
//            return null;
//        }
//
//        $arr = new InferType\KeyedArrayType(isList: false);
//
//        $arr->items = collect($toRelationshipsReturnType instanceof InferType\KeyedArrayType ? $toRelationshipsReturnType->items : [])
//            ->merge($propertiesRelationshipsType instanceof InferType\KeyedArrayType ? $propertiesRelationshipsType->items : [])
//            ->map(function (InferType\ArrayItemType_ $t) {
//                $t->isOptional = true;
//                $t->value = new InferType\KeyedArrayType([
//                    new InferType\ArrayItemType_(
//                        key: 'data',
//                        value: $t->value instanceof InferType\FunctionType ? $t->value->returnType : $t->value,
//                    ),
//                ]);
//
//                return $t;
//            })
//            ->all();
//
//        return $arr;
//    }
//
//    public function getLinksType(): ?KeyedArrayType
//    {
//        $linksType = $this->getMethodReturnType('toLinks');
//
//        return $linksType instanceof KeyedArrayType ? $linksType : null;
//    }
//
//    private function normalizeRelationshipsPropertyDefault(?Type $defaultType): ?InferType\KeyedArrayType
//    {
//        if (! $defaultType instanceof InferType\KeyedArrayType) {
//            return null;
//        }
//
//        $modelType = $this->getModelTypeOfResource();
//
//        $arrayType = clone $defaultType;
//        $arrayType->items = collect($arrayType->items)
//            ->map(function (InferType\ArrayItemType_ $t) use ($modelType) {
//                $newType = clone $t;
//
//                if ((is_int($newType->key) || $newType->key === null) && $this->isClassName($newType->value)) {
//                    $className = $this->getClassName($newType->value);
//
//                    if (! $guessedClass = $this->guessResourceClass($className)) {
//                        return null;
//                    }
//
//                    $relationshipType = $modelType?->getPropertyType($className) ?? new InferType\UnknownType;
//                    $relationshipIsMany = $relationshipType->isInstanceOf(Collection::class);
//
//                    $newType->key = $className;
//                    $newType->value = $relationshipIsMany
//                        ? new InferType\Generic(JsonApiResourceCollection::class, [new InferType\UnknownType, new InferType\UnknownType, new ObjectType($guessedClass)])
//                        : new ObjectType($guessedClass);
//
//                    return $newType;
//                }
//
//                if (! $this->isClassName($t->value)) {
//                    return null;
//                }
//
//                $relationshipType = ($newType->key ? $modelType?->getPropertyType((string) $newType->key) : null) ?? new InferType\UnknownType;
//                $relationshipIsMany = $relationshipType->isInstanceOf(Collection::class);
//
//                $newType->value = $relationshipIsMany
//                    ? new InferType\Generic(JsonApiResourceCollection::class, [new InferType\UnknownType, new InferType\UnknownType, new ObjectType($this->getClassName($t->value))])
//                    : new ObjectType($this->getClassName($t->value));
//
//                return $newType;
//            })
//            ->filter()
//            ->values()
//            ->all();
//
//        return $arrayType;
//    }
//
//    /**
//     * @see https://github.com/timacdonald/json-api/blob/main/src/Concerns/Relationships.php#L191
//     */
//    private function guessResourceClass(string $relationship): ?string
//    {
//        $relationship = Str::of($relationship);
//
//        foreach ([
//            "App\\Http\\Resources\\{$relationship->singular()->studly()}Resource",
//            "App\\Http\\Resources\\{$relationship->studly()}Resource",
//        ] as $class) {
//            if (class_exists($class)) {
//                return $class;
//            }
//        }
//
//        return null;
//    }

    private function normalizeAttributesPropertyDefault(?Type $defaultType): ?KeyedArrayType
    {
        if (! $defaultType instanceof InferType\KeyedArrayType) {
            return null;
        }

        $modelType = $this->getModelTypeOfResource();

        $arrayType = clone $defaultType;
        $arrayType->items = collect($arrayType->items)
            ->filter(fn (InferType\ArrayItemType_ $t) => $t->value instanceof InferType\Literal\LiteralStringType)
            ->map(function (InferType\ArrayItemType_ $t) use ($modelType) {
                /** @var InferType\Literal\LiteralStringType $value */
                $value = $t->value;

                $newType = clone $t;

                $newType->key = $value->value;
                $newType->value = $modelType?->getPropertyType($value->value) ?? new InferType\StringType;

                return $newType;
            })
            ->all();

        return $arrayType;
    }

    private function getModelTypeOfResource(): ?ObjectType
    {
        try {
            $type = JsonResourceHelper::modelType($this->definition);

            if (! $type instanceof ObjectType) {
                return null;
            }

            return $type;
        } catch (Throwable) {
            // Anything may go wrong here, but that is fine, we'll just fall back to string.
        }

        return null;
    }

    private function getMethodReturnType(string $method): ?Type
    {
        if (! $this->definition->hasMethodDefinition($method)) {
            return null;
        }

        return ReferenceTypeResolver::getInstance()->resolve(
            new GlobalScope,
            (new MethodCallReferenceType(new ObjectType($this->name), $method, arguments: []))
        );
    }

//    /**
//     * @phpstan-assert-if-true InferType\Contracts\LiteralString $value
//     */
//    private function isClassName(Type $value): bool
//    {
//        return $value instanceof InferType\Contracts\LiteralString;
//    }
//
//    private function getClassName(InferType\Contracts\LiteralString $value): string
//    {
//        return $value->getValue();
//    }
}
