<?php

namespace Dedoc\Scramble\Support\Type;

use Closure;
use Illuminate\Support\Arr;

class TypePathSet
{
    /**
     * @param  TypePath[]  $paths
     */
    public function __construct(public array $paths) {}

    /**
     * @return Type|Type[]|null
     */
    public function getFrom(Type $type): Type|array|null
    {
        /** @var TypePath[] $paths */
        $paths = Arr::sortDesc($this->paths, fn (TypePath $t) => count($t->items));

        foreach ($paths as $path) {
            if ($foundType = $path->getFrom($type)) {
                return $foundType;
            }
        }

        return null;
    }

    /**
     * @param  Closure(Type): bool  $cb
     */
    public static function find(Type $type, Closure $cb): self
    {
        $types = $type instanceof Union ? $type->types : [$type];

        return new self(array_values(array_filter(array_map(
            fn ($t) => TypePath::findFirst($t, $cb),
            $types,
        ))));
    }
}
