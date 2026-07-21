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

        $knownLoadedRelations = $this->getKnownLoadedRelations($modelType);

        if ($knownLoadedRelations === null) {
            return $this->defaultOrAnonymous($type);
        }

        if (! $variants = $this->getVariants($type->name)) {
            return $this->anonymous($type, $knownLoadedRelations);
        }

        return $this->doMatch($variants, $knownLoadedRelations);
    }

    /**
     * @param  SchemaVariant[]  $variants
     * @param  string[]  $knownLoadedRelations
     * @return void
     */
    private function doMatch(array $variants, array $knownLoadedRelations): JsonResourceVariant
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
        );
    }

    public function anonymous(ObjectType $type, array $knownLoadedRelations = []): JsonResourceVariant
    {
        return JsonResourceVariant::fromSchemaVariant(
            new SchemaVariant('', []),
            $knownLoadedRelations,
            isAnonymous: true,
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
