<?php

namespace Dedoc\Scramble\Reflection;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\InferExtensions\ModelExtension;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\FlattensMergeValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection as JsonApiAnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\Concerns\ResolvesJsonApiElements;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * @see JsonApiResource
 */
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

    /**
     * @return JsonApiRelationship[]
     */
    public function getRelationshipItems(): array
    {
        $type = $this->getRelationshipsType();

        if (! $type) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => $this->decodeRelationshipItem($item),
            $type->items,
        )));
    }

    /**
     * Returns a flat list of all relationships at every nesting level, with dotted names.
     *
     * @return JsonApiRelationship[]
     */
    public function getNestedRelationshipItems(int $maxRelationshipDepth, string $prefix = ''): array
    {
        if (! $maxRelationshipDepth) {
            return [];
        }

        $result = [];
        foreach ($this->getRelationshipItems() as $relationship) {
            $fullName = implode('.', array_filter([$prefix, $relationship->name]));

            $result[] = new JsonApiRelationship($fullName, $relationship->resourceType, $relationship->isCollection);

            $result = array_merge(
                $result,
                ReflectionJsonApiResource::createForClass($relationship->resourceType->name)->getNestedRelationshipItems(
                    $maxRelationshipDepth - 1,
                    $fullName,
                ),
            );
        }

        return $result;
    }

    private function decodeRelationshipItem(InferType\ArrayItemType_ $item): ?JsonApiRelationship
    {
        if (! is_string($item->key) || ! $item->value instanceof ObjectType) {
            return null;
        }

        if ($item->value->isInstanceOf(JsonApiAnonymousResourceCollection::class)) {
            $resourceType = ResourceCollectionTypeManager::make($item->value)->getCollectedType();
            $isCollection = true;
        } elseif ($item->value->isInstanceOf(JsonApiResource::class)) {
            $resourceType = $item->value;
            $isCollection = false;
        } else {
            return null;
        }

        if (! $resourceType instanceof ObjectType || ! $resourceType->isInstanceOf(JsonApiResource::class)) {
            return null;
        }

        return new JsonApiRelationship($item->key, $resourceType, $isCollection, $item->getAttribute('docNode')); // @phpstan-ignore argument.type
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
            /** @var string $morphAlias */
            $morphAlias = Relation::getMorphAlias($modelType->name); // @phpstan-ignore argument.type
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
            $instanceModel = $instanceModel instanceof InferType\TemplateType ? $instanceModel->is : $instanceModel;

            return $instanceModel instanceof InferType\ObjectType ? $instanceModel : null;
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
                    $relationshipName = $this->getLiteralStringValue($newType->value);

                    $relationshipType = $modelType?->getPropertyType($relationshipName) ?? new InferType\UnknownType;
                    $relationshipIsMany = $relationshipType->isInstanceOf(Collection::class);

                    $guessedClass = $this->guessResourceClassFromRelationship($relationshipType);

                    $newType->key = $relationshipName;
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
     * Attempts to find the related model's resource class name from a relationship type.
     * In case resource name cannot be found, falls back to {@see JsonApiResource::class} {@see ResolvesJsonApiElements::compileResourceRelationshipUsingResolver() Line 256}
     */
    private function guessResourceClassFromRelationship(Type $relationshipType): string
    {
        $relatedModel = (new InferType\TypeWalker)->first($relationshipType, fn (Type $t) => $t->isInstanceOf(Model::class));
        if ($relatedModel instanceof InferType\TemplateType) {
            $relatedModel = $relatedModel->is;
        }
        if (! $relatedModel instanceof ObjectType) {
            return JsonApiResource::class;
        }

        $guessedRelatedResource = $this->guessResourceClass($relatedModel->name);
        /**
         * If $guessedRelatedResource is JsonResource::class, it means that the type of `toResponse` was
         * inferred from Model's `toResponse` definition (not via {@see ModelExtension}). In runtime this
         * is causing the failure, but `JsonApiResource` wraps this call in `rescue`, and in case
         * of exception, returns `JsonApiResource` {@see ResolvesJsonApiElements::compileResourceRelationshipUsingResolver() Line 256}
         */
        if (! $guessedRelatedResource || $guessedRelatedResource === JsonResource::class) {
            return JsonApiResource::class;
        }

        return $guessedRelatedResource;
    }

    private function guessResourceClass(string $modelClass): ?string
    {
        $modelType = new ObjectType($modelClass);

        $resourceType = ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new MethodCallReferenceType($modelType, 'toResource', []),
            );

        return $resourceType instanceof ObjectType ? $resourceType->name : null;
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
