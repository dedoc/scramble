<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\AbstractType;
use Dedoc\Scramble\Support\Type\Reference\Dependency\Dependency;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\Type;

abstract class AbstractReferenceType extends AbstractType
{
    public function isSame(Type $type)
    {
        if (! $type instanceof static) {
            return false;
        }

        // @todo: revisit, maybe this either not optimal or there is a better way.
        return $type->toString() === $this->toString();
    }

    /**
     * This is the list of class names and functions (or 'self') that are dependencies for
     * the given reference. The reference can be resolved after these dependencies are analyzed.
     *
     * @return Dependency[]
     */
    abstract public function dependencies(): array;

    public static function getDependencies(Type|string|array $type)
    {
        if (! is_array($type)) {
            $type = [$type];
        }

        return collect($type)
            ->flatMap(function ($type) {
                if (is_string($type)) {
                    return [$type];
                }

                if ($type instanceof SelfType) {
                    return [$type->name];
                }

                if ($type instanceof AbstractReferenceType) {
                    return $type->dependencies();
                }

                return null;
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
