<?php

namespace Dedoc\Scramble\Support\JsonResource;

use Dedoc\Scramble\Attributes\SchemaVariant;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;

class JsonResourceVariant
{
    public function __construct(
        protected SchemaVariant $variant,
        protected array $loadedRelations,
        protected bool $isAnonymous = false,
        protected ?KeyedArrayType $eagerLoads = null,
    ) {}

    public static function fromSchemaVariant(
        SchemaVariant $variant,
        array $loadedRelations,
        bool $isAnonymous = false,
        ?KeyedArrayType $eagerLoads = null,
    ): self {
        return new self($variant, $loadedRelations, $isAnonymous, $eagerLoads);
    }

    public function reference(Components $components): Reference
    {
        return new Reference('schemas', $this->variant->name, $components);
    }

    public function filterReferencableFields(KeyedArrayType $array): KeyedArrayType
    {
        if ($this->isAnonymous()) {
            return $this->withPropagatedNestedEagerLoads($array->clone());
        }

        $array = $this->withPropagatedNestedEagerLoads($array->clone());

        $newItems = collect($array->items)
            ->filter(function (ArrayItemType_ $t) {
                $conditionalRelation = $this->getConditionalRelation($t);

                if (! $conditionalRelation) {
                    return true;
                }

                if ($this->variant->whenLoaded === '*') {
                    return true;
                }

                return in_array($conditionalRelation, $this->variant->whenLoaded, strict: true);
            })
            ->map(function (ArrayItemType_ $t) {
                $conditionalRelation = $this->getConditionalRelation($t);

                if (! $conditionalRelation) {
                    return $t;
                }

                $this->requireArrayItemType($t);
                $this->stripNestedModelRelations($t->value);

                return $t;
            })
            ->values()
            ->all();

        return new KeyedArrayType($newItems);
    }

    private function getConditionalRelation(ArrayItemType_ $item): ?string
    {
        if ($relation = $item->value->getAttribute('conditionalRelation')) {
            return $relation;
        }

        $nestedType = (new TypeWalker)->first(
            $item->value,
            fn (Type $t) => (bool) $t->getAttribute('conditionalRelation'),
        );

        return $nestedType?->getAttribute('conditionalRelation');
    }

    private function requireArrayItemType(ArrayItemType_ $t): void
    {
        if ($t->isOptional) {
            $t->isOptional = false;
        }

        if ($t->value instanceof Union && collect($t->value->types)->some(fn ($t) => $t->isInstanceOf(MissingValue::class))) {
            $t->value = Union::wrap(collect($t->value->types)->reject(fn ($t) => $t->isInstanceOf(MissingValue::class))->values()->all())
                ->mergeAttributes($t->value->attributes());
        }

        if ($t->value instanceof Generic && $t->value->isInstanceOf(JsonResource::class)) {
            $resource = $t->value->templateTypes[0] ?? null;

            if ($resource instanceof Union && collect($resource->types)->some(fn ($t) => $t->isInstanceOf(MissingValue::class))) {
                $t->value->templateTypes[0] = Union::wrap(collect($resource->types)->reject(fn ($t) => $t->isInstanceOf(MissingValue::class))->values()->all())
                    ->mergeAttributes($resource->attributes());
            }
        }

        if (
            $t->value instanceof Generic
            && $t->value->isInstanceOf(MergeValue::class)
            && $this->getConditionalRelation($t)
        ) {
            $t->value->templateTypes[0] = new LiteralBooleanType(true);
        }
    }

    public function isAnonymous()
    {
        return $this->isAnonymous;
    }

    public function filterLoadedFields(KeyedArrayType $array): KeyedArrayType
    {
        $array = $this->withPropagatedNestedEagerLoads($array->clone());

        $referencableFields = $this->filterReferencableFields($array);

        $requiredInReferencable = $this->isAnonymous()
            ? []
            : collect($referencableFields->items)
                ->map(fn (ArrayItemType_ $t) => $this->getConditionalRelation($t))
                ->filter()
                ->values()
                ->all();

        $newItems = collect($array->items)
            ->filter(function (ArrayItemType_ $t) use ($requiredInReferencable) {
                $conditionalRelation = $this->getConditionalRelation($t);

                if (! $conditionalRelation) {
                    return false;
                }

                if (! in_array($conditionalRelation, $this->loadedRelations, strict: true)) {
                    return false;
                }

                if (! in_array($conditionalRelation, $requiredInReferencable, strict: true)) {
                    return true;
                }

                return ! $this->isAnonymous() && $this->hasNestedLoadedRelations($t->value);
            })
            ->map(function (ArrayItemType_ $t) use ($requiredInReferencable) {
                $conditionalRelation = $this->getConditionalRelation($t);
                $isNestedSpecialization = $conditionalRelation
                    && in_array($conditionalRelation, $requiredInReferencable, strict: true);

                $this->requireArrayItemType($t);

                // Nested specialization only refines the property type; requiredness
                // already comes from the named variant schema.
                if ($isNestedSpecialization) {
                    $t->isOptional = true;
                }

                return $t;
            })
            ->values()
            ->all();

        return new KeyedArrayType($newItems);
    }

    private function withPropagatedNestedEagerLoads(KeyedArrayType $array): KeyedArrayType
    {
        if (! $this->eagerLoads) {
            return $array;
        }

        foreach ($array->items as $item) {
            $relation = $this->getConditionalRelation($item);

            if (! $relation) {
                continue;
            }

            $this->assignRelations(
                $item->value,
                $this->peelNestedEagerLoads($relation),
            );
        }

        return $array;
    }

    /**
     * @return list<string>
     */
    private function peelNestedEagerLoads(string $relationName): array
    {
        $nested = [];
        $prefix = $relationName.'.';

        foreach ($this->eagerLoads->items as $item) {
            if (! $item->value instanceof LiteralString) {
                continue;
            }

            $value = $item->value->getValue();

            if (str_starts_with($value, $prefix)) {
                $nested[] = substr($value, strlen($prefix));
            }
        }

        return $nested;
    }

    /**
     * @param  list<string>  $relations
     */
    private function assignRelations(Type $type, array $relations): void
    {
        $relationsType = new KeyedArrayType(
            array_map(
                fn (string $relation) => new ArrayItemType_(null, new LiteralStringType($relation)),
                $relations,
            ),
        );

        (new TypeWalker)->walk($type, function (Type $t) use ($relationsType) {
            if ($t instanceof ObjectType && $t->isInstanceOf(Model::class) && ! $t->isInstanceOf(JsonResource::class)) {
                $t->propertyTypes['relations'] = $relationsType->clone();
            }
        });
    }

    private function hasNestedLoadedRelations(Type $type): bool
    {
        return (new TypeWalker)->first(
            $type,
            function (Type $t) {
                if (! $t instanceof ObjectType || ! $t->isInstanceOf(Model::class) || $t->isInstanceOf(JsonResource::class)) {
                    return false;
                }

                $relations = $t->propertyTypes['relations'] ?? null;

                return $relations instanceof KeyedArrayType && count($relations->items) > 0;
            },
        ) !== null;
    }

    private function stripNestedModelRelations(Type $type): void
    {
        (new TypeWalker)->walk($type, function (Type $t) {
            if (! $t instanceof ObjectType || ! $t->isInstanceOf(Model::class) || $t->isInstanceOf(JsonResource::class)) {
                return;
            }

            if (isset($t->propertyTypes['relations'])) {
                $t->propertyTypes['relations'] = new KeyedArrayType([]);
            }
        });
    }
}
