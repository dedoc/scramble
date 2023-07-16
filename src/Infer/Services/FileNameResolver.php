<?php

namespace Dedoc\Scramble\Infer\Services;

use PhpParser\NameContext;
use PhpParser\Node\Name;

class FileNameResolver
{
    private NameContext $nameContext;

    public function __construct(NameContext $nameContext)
    {
        $this->nameContext = $nameContext;
    }

    public function __invoke(string $shortName): string
    {
        $name = $this->nameContext->getResolvedName(new Name([$shortName]), 1)->toString();

        return class_exists($name) || interface_exists($name) ? $name : $shortName;
    }
}
