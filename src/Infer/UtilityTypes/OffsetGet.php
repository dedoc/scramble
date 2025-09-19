<?php

namespace Dedoc\Scramble\Infer\UtilityTypes;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Arr;

/**
 * @internal
 */
class OffsetGet implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        $type = $event->type;

        if (! $type instanceof Generic) {
            throw new \InvalidArgumentException('Type must be generic');
        }

        $target = $this->getTarget($type);

        if ($this->shouldDefferResolution($target)) {
            return null;
        }

        if (! $target instanceof KeyedArrayType && ! $target instanceof ArrayType) {
            return new UnknownType;
        }

        if (! $pathType = $this->getPath($type)) {
            return $target;
        }

        if ($target instanceof ArrayType) {
            return $target->value;
        }

        $path = $this->normalizePath($pathType);
        if ($path === null) {
            return new UnknownType;
        }

        return $this->getOffsetByPath($target->clone(), $path);
    }

    private function getOffsetByPath(KeyedArrayType $target, string|int $path): Type
    {
        $arrayItem = Arr::first(
            $target->items,
            fn (ArrayItemType_ $t) => $t->key === $path,
        );

        if (! $arrayItem) {
            return new UnknownType;
        }

        return $arrayItem->value->mergeAttributes($arrayItem->attributes());
    }

    private function getTarget(Generic $type): ?Type
    {
        return $type->templateTypes[0] ?? null;
    }

    private function getPath(Generic $type): ?LiteralType
    {
        $path = $type->templateTypes[1] ?? null;

        return $path instanceof LiteralType ? $path : null;
    }

    private function normalizePath(LiteralType $path): string|int|null
    {
        if ($path instanceof LiteralString || $path instanceof LiteralIntegerType) {
            return $path->getValue();
        }

        return null;
    }

    private function shouldDefferResolution(?Type $target): bool
    {
        if (! $target) {
            return false;
        }

        if ($target instanceof TemplateType) {
            return true;
        }

        return $target instanceof Generic && $target->isInstanceOf(ResolvingType::class);
    }
}
