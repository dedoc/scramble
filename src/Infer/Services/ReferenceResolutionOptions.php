<?php

namespace Dedoc\Scramble\Infer\Services;

class ReferenceResolutionOptions
{
    public $unknownClassResolver;

    public function __construct(
        $unknownClassResolver = null,
        public bool $shouldResolveResultingReferencesIntoUnknowns = false,
    )
    {
        $this->unknownClassResolver = $unknownClassResolver ?: fn () => null;
    }

    public static function make()
    {
        return new self();
    }

    public function resolveUnknownClassesUsing(callable $unknownClassResolver)
    {
        $this->unknownClassResolver = $unknownClassResolver;

        return $this;
    }

    public function resolveResultingReferencesIntoUnknown(bool $shouldResolveResultingReferencesIntoUnknowns)
    {
        $this->shouldResolveResultingReferencesIntoUnknowns = $shouldResolveResultingReferencesIntoUnknowns;

        return $this;
    }
}
