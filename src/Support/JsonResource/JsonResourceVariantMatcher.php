<?php

namespace Dedoc\Scramble\Support\JsonResource;

use Dedoc\Scramble\Attributes\SchemaVariant;
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
            return $this->anonymous($type);
        }

        if (! $modelType = $this->getModelFromResource($type)) {
            return $this->defaultOrAnonymous($type);
        }

        $eagerLoads = $this->getEagerLoads($modelType);

        if ($eagerLoads === null) {
            return $this->defaultOrAnonymous($type);
        }

        $knownLoadedRelations = $this->normalizeLoadedRelations($eagerLoads);

        if (! $variants = $this->getVariants($type->name)) {
            return $this->anonymous($type, $knownLoadedRelations, $eagerLoads);
        }

        return $this->doMatch($variants, $knownLoadedRelations, $eagerLoads);
    }

    /**
     * @param  SchemaVariant[]  $variants
     * @param  string[]  $knownLoadedRelations
     */
    private function doMatch(array $variants, array $knownLoadedRelations, KeyedArrayType $eagerLoads): JsonResourceVariant
    {
        $variants = collect($variants)
            ->sort(function (SchemaVariant $v) {
                return $v->default ? -1 : 0;
            })
            ->sortByDesc(function (SchemaVariant $variant) use ($knownLoadedRelations) {
                $variantRelations = $variant->whenLoaded === '*'
                    ? $knownLoadedRelations
                    : $variant->whenLoaded;

                $specificityMultiplier = $knownLoadedRelations === $variant->whenLoaded
                    ? ($variant->whenLoaded === '*' ? 1 : 10)
                    : 1;

                return $specificityMultiplier * count(array_intersect($variantRelations, $knownLoadedRelations));
            });

        return JsonResourceVariant::fromSchemaVariant(
            $variants->first(),
            $knownLoadedRelations,
            eagerLoads: $eagerLoads,
        );
    }

    /**
     * @param  string[]  $knownLoadedRelations
     */
    public function anonymous(ObjectType $type, array $knownLoadedRelations = [], ?KeyedArrayType $eagerLoads = null): JsonResourceVariant
    {
        return JsonResourceVariant::fromSchemaVariant(
            new SchemaVariant('', []),
            $knownLoadedRelations,
            isAnonymous: true,
            eagerLoads: $eagerLoads,
        );
    }

    private function defaultOrAnonymous(ObjectType $type): JsonResourceVariant
    {
        if ($default = $this->findDefaultVariant($type->name)) {
            return JsonResourceVariant::fromSchemaVariant($default, []);
        }

        return $this->anonymous($type);
    }

    private function findDefaultVariant(string $jsonClassName): ?SchemaVariant
    {
        foreach ($this->getVariants($jsonClassName) as $variant) {
            if ($variant->default) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * @param  class-string<JsonResource>  $jsonClassName
     * @return SchemaVariant[]
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
            $reflection->getAttributes(SchemaVariant::class),
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

    private function getEagerLoads(ObjectType $modelType): ?KeyedArrayType
    {
        $explicitRelationsType = $modelType->propertyTypes['relations'] ?? null;

        return $explicitRelationsType instanceof KeyedArrayType
            ? $explicitRelationsType
            : null;
    }

    /**
     * @return string[]
     */
    private function normalizeLoadedRelations(KeyedArrayType $eagerLoads): array
    {
        $relations = array_values(array_filter(array_map(
            fn (ArrayItemType_ $t) => $t->value instanceof LiteralString ? $t->value->getValue() : null,
            $eagerLoads->items,
        )));

        return collect($relations)
            ->map(fn (string $relation) => explode('.', $relation)[0])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
