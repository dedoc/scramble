<?php

namespace Dedoc\Scramble\Infer\Configuration;

interface DefinitionMatcher
{
    public function matches(string $class): bool;
}
