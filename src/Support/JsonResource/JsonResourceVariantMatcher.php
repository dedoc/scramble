<?php

namespace Dedoc\Scramble\Support\JsonResource;

use Dedoc\Scramble\Attributes\JsonResourceSchemaVariant;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
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
    public function match(ObjectType $type): ?JsonResourceVariant
    {
        if (! $type->isInstanceOf(JsonResource::class)) {
            return null;
        }

        if (! $variants = $this->getVariants($type->name)) {
            return null;
        }

        if (! $modelType = $this->getModelFromResource($type)) {
            return null;
        }

        if (! $knownLoadedRelations = $this->getKnownLoadedRelations($modelType)) {
            return null;
        }

        return $this->doMatch($variants, $knownLoadedRelations);
    }

    /**
     * @param JsonResourceSchemaVariant[] $variants
     * @param string[] $knownLoadedRelations
     * @return void
     */
    private function doMatch(array $variants, array $knownLoadedRelations): JsonResourceVariant
    {
        $knownLoadedRelations = collect($knownLoadedRelations)
            ->map(fn (string $relation) => explode('.', $relation)[0])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $variants = collect($variants)
            ->sort(function (JsonResourceSchemaVariant $v) {
                return $v->fallback ? -1 : 0;
            })
            ->sortByDesc(function (JsonResourceSchemaVariant $variant) use ($knownLoadedRelations) {
                $variantRelations = $variant->withLoaded === '*'
                    ? $knownLoadedRelations
                    : $variant->withLoaded;

                return count(array_intersect($variantRelations, $knownLoadedRelations));
            });

        return JsonResourceVariant::fromJsonResourceSchemaVariant(
            $variants->first(),
            $knownLoadedRelations,
        );
    }

    /**
     * @param class-string<JsonResource> $jsonClassName
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
        return (new TypeWalker())->first(
            $type,
            fn (Type $t) => $t->isInstanceOf(Model::class),
        );
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

        return array_values(array_filter(array_map(
            fn (ArrayItemType_ $t) => $t->value instanceof LiteralString ? $t->value->getValue() : null,
            $explicitRelationsType->items,
        )));
    }
}
