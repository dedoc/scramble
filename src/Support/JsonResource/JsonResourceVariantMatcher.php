<?php

namespace Dedoc\Scramble\Support\JsonResource;

use Dedoc\Scramble\Attributes\JsonResourceSchemaVariant;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;

class JsonResourceVariantMatcher
{
    public function __construct(private Index $index) {}

    /**
     * @return JsonResourceVariant|null
     */
    public function match(ObjectType $type): JsonResourceVariant
    {
        if (! $type->isInstanceOf(JsonResource::class)) {
            return $this->default($type);
        }

        if (! $modelType = $this->getModelFromResource($type)) {
            return $this->fallbackOrDefault($type);
        }

        $knownLoadedRelations = $this->getKnownLoadedRelations($modelType);

        if ($knownLoadedRelations === null) {
            return $this->fallbackOrDefault($type);
        }

        if (! $variants = $this->getVariants($type->name)) {
            return $this->default($type, $knownLoadedRelations);
        }

        return $this->doMatch($variants, $knownLoadedRelations);
    }

    /**
     * @param  JsonResourceSchemaVariant[]  $variants
     * @param  string[]  $knownLoadedRelations
     * @return void
     */
    private function doMatch(array $variants, array $knownLoadedRelations): JsonResourceVariant
    {
        $variants = collect($variants)
            ->sort(function (JsonResourceSchemaVariant $v) {
                return $v->fallback ? -1 : 0;
            })
            ->sortByDesc(function (JsonResourceSchemaVariant $variant) use ($knownLoadedRelations) {
                $variantRelations = $variant->withLoaded === '*'
                    ? $knownLoadedRelations
                    : $variant->withLoaded;

                $specificityMultiplier = $knownLoadedRelations === $variant->withLoaded
                    ? ($variant->withLoaded === '*' ? 1 : 10)
                    : 1;

                return $specificityMultiplier * count(array_intersect($variantRelations, $knownLoadedRelations));
            });

        return JsonResourceVariant::fromJsonResourceSchemaVariant(
            $variants->first(),
            $knownLoadedRelations,
        );
    }

    public function default(ObjectType $type, array $knownLoadedRelations = []): JsonResourceVariant
    {
        return JsonResourceVariant::fromJsonResourceSchemaVariant(
            new JsonResourceSchemaVariant('', []),
            $knownLoadedRelations,
            isDefault: true,
        );
    }

    private function fallbackOrDefault(ObjectType $type): JsonResourceVariant
    {
        if ($fallback = $this->findFallbackVariant($type->name)) {
            return JsonResourceVariant::fromJsonResourceSchemaVariant($fallback, []);
        }

        return $this->default($type);
    }

    private function findFallbackVariant(string $jsonClassName): ?JsonResourceSchemaVariant
    {
        foreach ($this->getVariants($jsonClassName) as $variant) {
            if ($variant->fallback) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * @param  class-string<JsonResource>  $jsonClassName
     * @return JsonResourceSchemaVariant[]
     */
    private function getVariants(string $jsonClassName): array
    {
        try {
            $reflection = new ReflectionClass($jsonClassName);
        } catch (ReflectionException) {
            return [];
        }

        return array_values(array_map(
            fn (ReflectionAttribute $attr) => $attr->newInstance(),
            $reflection->getAttributes(JsonResourceSchemaVariant::class),
        ));
    }

    private function getModelFromResource(ObjectType $type): ?ObjectType
    {
        $modelType = (new TypeWalker)->first(
            $type,
            fn (Type $t) => $t->isInstanceOf(Model::class),
        );

        if ($modelType instanceof TemplateType) {
            $modelType = $modelType->is;
        }

        return $modelType instanceof ObjectType ? $modelType : null;
    }

    /**
     * @return string[]|null
     */
    private function getKnownLoadedRelations(ObjectType $modelType): ?array
    {
        $explicitRelationsType = $modelType->propertyTypes['relations'] ?? null;

        if (! $explicitRelationsType instanceof KeyedArrayType) {
            return null;
        }

        $relations = array_values(array_filter(array_map(
            fn (ArrayItemType_ $t) => $t->value instanceof LiteralString ? $t->value->getValue() : null,
            $explicitRelationsType->items,
        )));

        return collect($relations)
            ->map(fn (string $relation) => explode('.', $relation)[0])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
