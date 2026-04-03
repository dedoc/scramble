<?php

namespace Dedoc\Scramble\Reflection;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\FlattensMergeValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection as JsonApiAnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\Concerns\ResolvesJsonApiElements;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class ReflectionJsonApiResource
{
    use FlattensMergeValues;

    private function __construct(public readonly string $name, public readonly ClassDefinition $definition)
    {
    }

    public static function createForClass(string $class): self
    {
        return new self($class, app(Infer::class)->analyzeClass($class));
    }

    public function getAttributesType(): ?InferType\KeyedArrayType
    {
        $propertiesAttributesType = $this->getPropertyDefaultType('attributes');
        $propertiesAttributesType = $propertiesAttributesType instanceof KeyedArrayType ? $propertiesAttributesType : null;

        $toAttributesReturnType = $this->getMethodReturnType('toAttributes');
        $toAttributesReturnType = $toAttributesReturnType instanceof KeyedArrayType ? $toAttributesReturnType : null;

        if (
            ! $propertiesAttributesType instanceof InferType\KeyedArrayType
            && ! $toAttributesReturnType instanceof InferType\KeyedArrayType
        ) {
            return null;
        }

        return $this->normalizeAttributesType($toAttributesReturnType ?: $propertiesAttributesType);
    }

    public function getRelationshipsType(): ?KeyedArrayType
    {
        $propertiesRelationshipsType = $this->getPropertyDefaultType('relationships');
        $propertiesRelationshipsType = $propertiesRelationshipsType instanceof KeyedArrayType ? $propertiesRelationshipsType : null;

        $toRelationshipsReturnType = $this->getMethodReturnType('toRelationships');
        $toRelationshipsReturnType = $toRelationshipsReturnType instanceof KeyedArrayType ? $toRelationshipsReturnType : null;

        if (
            ! $propertiesRelationshipsType instanceof InferType\KeyedArrayType
            && ! $toRelationshipsReturnType instanceof InferType\KeyedArrayType
        ) {
            return null;
        }

        return $this->normalizeRelationshipsType($toRelationshipsReturnType ?: $propertiesRelationshipsType);
    }

    public function getLinksType(): ?KeyedArrayType
    {
        $linksType = $this->getMethodReturnType('toLinks');

        return $linksType instanceof KeyedArrayType ? $linksType : null;
    }

    public function getMetaType(): ?KeyedArrayType
    {
        $metaType = $this->getMethodReturnType('toMeta');

        return $metaType instanceof KeyedArrayType ? $metaType : null;
    }

    public function getIdType(InferType\Generic $type): InferType\Type
    {
        if (! $modelType = $this->getModelTypeFromInstanceOrDeclaration($type)) {
            return new InferType\StringType;
        }


        return tap(new InferType\StringType, function ($t) use ($modelType) {
            $isUuid = ReflectionModel::createForClass($modelType->name)->isKeyUuid();

            if ($isUuid) {
                $t->setAttribute('format', 'uuid');
            }
        });
    }

    /**
     * @see ResolvesJsonApiElements::resolveResourceType
     */
    public function getTypeType(InferType\Generic $type): InferType\Type
    {
        $toTypeReturn = $this->getMethodReturnType('toType');
        if ($toTypeReturn instanceof InferType\Contracts\LiteralString) {
            return new LiteralStringType($toTypeReturn->getValue());
        }

        if ($this->name !== JsonApiResource::class) {
            $value = Str::of($this->name)->classBasename()->beforeLast('Resource')->snake()->pluralStudly()->toString();

            return new LiteralStringType($value);
        }

        if ($modelType = $this->getModelTypeFromInstanceOrDeclaration($type)) {
            $morphAlias = Relation::getMorphAlias($modelType->name);
            $base = $morphAlias !== $modelType->name ? $morphAlias : class_basename($modelType->name);
            $value = Str::of($base)->snake()->pluralStudly()->toString();

            return new LiteralStringType($value);
        }

        return new InferType\StringType;
    }

    private function getModelTypeFromInstanceOrDeclaration(InferType\Generic $type): ?ObjectType
    {
        $instanceModel = $type->templateTypes[0] ?? new InferType\UnknownType;
        if ($instanceModel->isInstanceOf(Model::class)) {
            return $instanceModel;
        }

        return $this->getModelType();
    }

    private function normalizeRelationshipsType(?Type $type): ?InferType\KeyedArrayType
    {
        if (! $type instanceof InferType\KeyedArrayType) {
            return null;
        }

        $modelType = $this->getModelType();

        $arrayType = clone $type;
        $arrayType->items = collect($arrayType->items)
            ->map(function (InferType\ArrayItemType_ $t) use ($modelType) {
                $newType = clone $t;
                $newType->value = $newType->value instanceof InferType\FunctionType
                    ? $newType->value->getReturnType()
                    : $newType->value;

                if ($newType->isNumericKey() && $this->isLiteralString($newType->value)) {
                    $className = $this->getLiteralStringValue($newType->value);

                    if (! $guessedClass = $this->guessResourceClass($className)) {
                        /**
                         * @see ResolvesJsonApiElements::compileResourceRelationshipUsingResolver() Line 256
                         */
                        $guessedClass = JsonApiResource::class;
                    }

                    $relationshipType = $modelType?->getPropertyType($className) ?? new InferType\UnknownType;
                    $relationshipIsMany = $relationshipType->isInstanceOf(Collection::class);

                    $newType->key = $className;
                    $newType->value = $relationshipIsMany
                        ? new InferType\Generic(JsonApiAnonymousResourceCollection::class, [new InferType\UnknownType, new InferType\UnknownType, new ObjectType($guessedClass)])
                        : new ObjectType($guessedClass);

                    return $newType;
                }

                if ($this->isLiteralString($t->value)) {
                    $relationshipType = ($newType->key ? $modelType?->getPropertyType((string) $newType->key) : null) ?? new InferType\UnknownType;
                    $relationshipIsMany = $relationshipType->isInstanceOf(Collection::class);

                    $newType->value = $relationshipIsMany
                        ? new InferType\Generic(JsonApiAnonymousResourceCollection::class, [new InferType\UnknownType, new InferType\UnknownType, new ObjectType($this->getLiteralStringValue($t->value))])
                        : new ObjectType($this->getLiteralStringValue($t->value));

                    return $newType;
                }

                return $newType;
            })
            ->filter()
            ->values()
            ->all();
        $arrayType->isList = KeyedArrayType::checkIsList($arrayType->items);
        return $arrayType;
    }

    /**
     * @todo
     * This is temporary implementation, under the hood model's toResource is called.
     */
    private function guessResourceClass(string $relationship): ?string
    {
        $relationship = Str::of($relationship);

        foreach ([
            "App\\Http\\Resources\\{$relationship->singular()->studly()}Resource",
            "App\\Http\\Resources\\{$relationship->studly()}Resource",
        ] as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    private function normalizeAttributesType(?Type $type): ?KeyedArrayType
    {
        if (! $type instanceof InferType\KeyedArrayType) {
            return null;
        }

        $modelType = $this->getModelType();

        $arrayType = clone $type;
        $arrayType->items = collect($arrayType->items)
            ->map(function (InferType\ArrayItemType_ $t) use ($modelType) {
                $newType = clone $t;

                if ($t->isNumericKey() && $this->isLiteralString($t->value)) {
                    $newType->key = $propertyName = $this->getLiteralStringValue($t->value);
                    $newType->value = $modelType?->getPropertyType($propertyName) ?? new InferType\StringType;

                    return $newType;
                }

                return $newType;
            })
            ->all();
        $arrayType->isList = KeyedArrayType::checkIsList($arrayType->items);

        return $arrayType;
    }

    private function getModelType(): ?ObjectType
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

    private function getPropertyDefaultType(string $name): ?Type
    {
        return $this->definition->getPropertyDefinition($name)?->defaultType;
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

    /**
     * @phpstan-assert-if-true InferType\Contracts\LiteralString $value
     */
    private function isLiteralString(Type $value): bool
    {
        return $value instanceof InferType\Contracts\LiteralString;
    }

    private function getLiteralStringValue(InferType\Contracts\LiteralString $value): string
    {
        return $value->getValue();
    }
}
