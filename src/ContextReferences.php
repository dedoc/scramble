<?php

namespace Dedoc\Scramble;

/** @internal */
class ContextReferences
{
    public function __construct(
        public readonly ContextReferencesCollection $schemas = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $responses = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $parameters = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $examples = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $requestBodies = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $headers = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $securitySchemes = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $links = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $callbacks = new ContextReferencesCollection,
        public readonly ContextReferencesCollection $pathItems = new ContextReferencesCollection,
    ) {}
}
