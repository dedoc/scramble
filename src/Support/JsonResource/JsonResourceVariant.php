<?php

namespace Dedoc\Scramble\Support\JsonResource;

use Dedoc\Scramble\Attributes\JsonResourceSchemaVariant;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Http\Resources\MissingValue;

class JsonResourceVariant
{
    public function __construct(
        protected JsonResourceSchemaVariant $variant,
        protected array $loadedRelations,
    )
    {
    }

    public static function fromJsonResourceSchemaVariant(JsonResourceSchemaVariant $variant, array $loadedRelations): self
    {
        return new self($variant, $loadedRelations);
    }

    public function reference(Components $components): Reference
    {
        return new Reference('schemas', $this->variant->name, $components);
    }

    public function filterReferencableFields(KeyedArrayType $array): KeyedArrayType
    {
        $newItems = collect($array->items)
            ->filter(function (ArrayItemType_ $t) {
                $conditionalRelation = $t->value->getAttribute('conditionalRelation');

                if (! $conditionalRelation) {
                    return true;
                }

                return in_array($conditionalRelation, $this->loadedRelations, strict: true);
            })
            ->map(function (ArrayItemType_ $t) {
                $conditionalRelation = $t->value->getAttribute('conditionalRelation');

                if (! $conditionalRelation) {
                    return $t;
                }

                if ($t->isOptional) {
                    $t->isOptional = false;

                    return $t;
                }

                if ($t->value instanceof Union && collect($t->value->types)->some(fn ($t) => $t->isInstanceOf(MissingValue::class))) {
                    $t->value = Union::wrap(collect($t->value->types)->reject(fn ($t) => $t->isInstanceOf(MissingValue::class))->values()->all());

                    return $t;
                }

                return $t;
            })
            ->values()
            ->all();

        return new KeyedArrayType($newItems);
    }

    public function isFallback(): bool
    {
        return $this->variant->fallback;
    }

    public function filterLoadedFields(KeyedArrayType $array): ?KeyedArrayType
    {
        return null;//new KeyedArrayType([]);
    }
}
