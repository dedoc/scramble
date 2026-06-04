<?php

namespace Dedoc\Scramble\Infer\Definition;

enum PropertyVisibility
{
    case Private;
    case Protected;
    case Public;

    public static function fromReflectionProperty(\ReflectionProperty $property): self
    {
        return match (true) {
            $property->isPrivate() => self::Private,
            $property->isProtected() => self::Protected,
            default => self::Public,
        };
    }
}
