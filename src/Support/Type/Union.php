<?php

namespace Dedoc\Scramble\Support\Type;

use Illuminate\Support\Arr;

class Union extends AbstractType
{
    /**
     * @param  Type[]  $types
     */
    public function __construct(public array $types) {}

    public function nodes(): array
    {
        return ['types'];
    }

    public function isSame(Type $type)
    {
        if (! $type instanceof static) {
            return false;
        }

        if (count($this->types) !== count($type->types)) {
            return false;
        }

        return collect($this->types)->every(fn (Type $t, $i) => $t->isSame($type->types[$i]));
    }

    public function widen(): Type
    {
        return app(TypeWidener::class)->widen($this->types)->mergeAttributes($this->attributes());
    }

    public function accepts(Type $otherType): bool
    {
        // If the other type is also a union, all of its subtypes must be accepted
        if ($otherType instanceof Union) {
            foreach ($otherType->types as $inner) {
                if (! $this->accepts($inner)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($this->types as $type) {
            if ($type->accepts($otherType)) {
                return true;
            }
        }

        return false;
    }

    public function acceptedBy(Type $otherType): bool
    {
        foreach ($this->types as $type) {
            if (! $otherType->accepts($type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Type|Type[]  $types
     */
    public static function wrap(...$types): Type
    {
        $types = Arr::wrap(...$types);

        $uniqueTypes = [];

        foreach (array_values($types) as $type) {
            $alreadyAdded = false;

            foreach ($uniqueTypes as $existingType) {
                if ($type->isSame($existingType)) {
                    $alreadyAdded = true;

                    break;
                }
            }

            if (! $alreadyAdded) {
                $uniqueTypes[] = $type;
            }
        }

        if (! count($uniqueTypes)) {
            return new VoidType;
        }

        if (count($uniqueTypes) === 1) {
            return $uniqueTypes[0];
        }

        return new self($uniqueTypes);
    }

    public function toString(): string
    {
        return implode('|', array_map(fn ($t) => $t->toString(), $this->types));
    }
}
