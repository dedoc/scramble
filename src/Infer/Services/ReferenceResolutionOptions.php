<?php

namespace Dedoc\Scramble\Infer\Services;

class ReferenceResolutionOptions
{
    public $unknownClassResolver;

    public function __construct(
        $unknownClassResolver = null,
        public bool $shouldResolveResultingReferencesIntoUnknowns = false,
        public bool $hasUnknownResolver = false,
    ) {
        $this->unknownClassResolver = $unknownClassResolver ?: fn () => null;
    }

    public static function make()
    {
        return new self();
    }

    public function resolveUnknownClassesUsing(callable $unknownClassResolver)
    {
        $this->unknownClassResolver = $unknownClassResolver;
        $this->hasUnknownResolver = true;

        return $this;
    }

    public function resolveResultingReferencesIntoUnknown(bool $shouldResolveResultingReferencesIntoUnknowns)
    {
        $this->shouldResolveResultingReferencesIntoUnknowns = $shouldResolveResultingReferencesIntoUnknowns;

        return $this;
    }
}
