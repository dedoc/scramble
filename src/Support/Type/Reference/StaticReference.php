<?php

namespace Dedoc\Scramble\Support\Type\Reference;

class StaticReference
{
    const STATIC = 'static';

    const SELF = 'self';

    const PARENT = 'parent';

    const KEYWORDS = [self::STATIC, self::SELF, self::PARENT];

    public function __construct(public string $keyword)
    {
        if (! in_array($this->keyword, self::KEYWORDS)) {
            throw new \InvalidArgumentException("[$this->keyword] keyword must be one of possible values.");
        }
    }

    public function isStatic()
    {
        return $this->keyword === static::STATIC;
    }

    public function isSelf()
    {
        return $this->keyword === static::SELF;
    }

    public function isParent()
    {
        return $this->keyword === static::PARENT;
    }

    public function toString()
    {
        return $this->keyword;
    }
}
