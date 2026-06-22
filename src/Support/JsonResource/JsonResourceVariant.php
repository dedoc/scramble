<?php

namespace Dedoc\Scramble\Support\JsonResource;

use Dedoc\Scramble\Attributes\JsonResourceSchemaVariant;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;

class JsonResourceVariant
{
    public function __construct(
        protected JsonResourceSchemaVariant $variant,
        protected array $loadedRelations,
        protected bool $isDefault = false,
    )
    {
    }

    public static function fromJsonResourceSchemaVariant(JsonResourceSchemaVariant $variant, array $loadedRelations, bool $isDefault = false): self
    {
        return new self($variant, $loadedRelations, $isDefault);
    }

    public function reference(Components $components): Reference
    {
        return new Reference('schemas', $this->variant->name, $components);
    }

    public function filterReferencableFields(KeyedArrayType $array): KeyedArrayType
    {
        if ($this->isDefault()) {
            return $array;
        }

        $array = $array->clone();

        $newItems = collect($array->items)
            ->filter(function (ArrayItemType_ $t) {
                $conditionalRelation = $this->getConditionalRelation($t);

                if (! $conditionalRelation) {
                    return true;
                }

                if ($this->variant->withLoaded === '*') {
                    return true;
                }

                return in_array($conditionalRelation, $this->variant->withLoaded, strict: true);
            })
            ->map(function (ArrayItemType_ $t) {
                $conditionalRelation = $this->getConditionalRelation($t);

                if (! $conditionalRelation) {
                    return $t;
                }

                $this->requireArrayItemType($t);

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
    }

    public function isDefault()
    {
        return $this->isDefault;
    }

    public function filterLoadedFields(KeyedArrayType $array): KeyedArrayType
    {
        $array = $array->clone();

        $referencableFields = $this->filterReferencableFields($array);

        $requiredInReferencable = $this->isDefault()
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

                return ! in_array($conditionalRelation, $requiredInReferencable, strict: true);
            })
            ->map(function (ArrayItemType_ $t) {
                $this->requireArrayItemType($t);

                return $t;
            })
            ->values()
            ->all();

        return new KeyedArrayType($newItems);
    }
}
