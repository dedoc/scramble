<?php

namespace Dedoc\Scramble\Support\TypeManagers;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Str;

/**
 * @see ResourceCollection
 */
class ResourceCollectionTypeManager
{
    public function __construct(private Generic $type, private Index $index) {}

    public function getCollectedType(): Generic|UnknownType
    {
        if ($inferredCollectedType = $this->getInferredCollectedType()) {
            return $inferredCollectedType;
        }

        if ($collectedTypeFromProperty = $this->guessCollectedTypeResourceName()) {
            return $collectedTypeFromProperty;
        }

        return new UnknownType;
    }

    private function getInferredCollectedType(): ?Generic
    {
        $collectsClassNameType = $this->type->templateTypes[/* TCollects */ 2] ?? null;

        if (! $collectsClassNameType instanceof ObjectType) {
            return $this->getCollectedTypeFromManualAnnotation();
        }

        return new Generic($collectsClassNameType->name, [new UnknownType]);
    }

    private function getCollectedTypeFromManualAnnotation(): ?Generic
    {
        $type = (new TypeWalker)->first(
            new Union([
                $this->type->templateTypes[0] ?? new UnknownType,
                $this->type->templateTypes[1] ?? new UnknownType,
            ]),
            fn (Type $t) => $t->isInstanceOf(JsonResource::class),
        );

        if (! $type instanceof ObjectType) {
            return null;
        }

        if (! $type instanceof Generic) {
            return new Generic($type->name);
        }

        return $type;
    }

    private function guessCollectedTypeResourceName(): ?Generic
    {
        if (! $classDefinition = $this->index->getClass($this->type->name)) {
            return null;
        }

        $collectsPropertyDefaultType = $classDefinition->getPropertyDefinition('collects')?->defaultType;
        if (
            $collectsPropertyDefaultType instanceof GenericClassStringType
            && $collectsPropertyDefaultType->type instanceof ObjectType
        ) {
            return new Generic($collectsPropertyDefaultType->type->name, [new UnknownType]);
        }

        if (
            str_ends_with($classDefinition->name, 'Collection') &&
            (class_exists($class = Str::replaceLast('Collection', '', $classDefinition->name)) ||
                class_exists($class = Str::replaceLast('Collection', 'Resource', $classDefinition->name)))
        ) {
            return new Generic($class, [new UnknownType]);
        }

        return null;
    }

    public static function make(ObjectType $type): self
    {
        return new self(
            $type instanceof Generic ? $type : new Generic($type->name),
            app(Index::class),
        );
    }

    public function getResponseType(): Generic
    {
        if ($this->isPaginatedResource()) {
            return new Generic(PaginatedResourceResponse::class, [$this->type]);
        }

        return new Generic(ResourceResponse::class, [$this->type]);
    }

    private function isPaginatedResource(): bool
    {
        $resourceType = $this->type->templateTypes[/* TResource */ 0] ?? null;

        if (! $resourceType instanceof ObjectType) {
            return false;
        }

        return $resourceType->isInstanceOf(AbstractPaginator::class)
            || $resourceType->isInstanceOf(AbstractCursorPaginator::class);
    }
}
