<?php

namespace Dedoc\Scramble\Infer\SourceLocators;

use Dedoc\Scramble\Infer\Contracts\SourceLocator;
use Dedoc\Scramble\Infer\Symbol;

class ReflectionSourceLocator implements SourceLocator
{
    /**
     * @throws \ReflectionException
     */
    public function getSource(Symbol $symbol): string
    {
        if ($symbol->kind === Symbol::KIND_FUNCTION) { // will throw for built-in fns
            $reflection = new \ReflectionFunction($symbol->name);
            return file_get_contents($reflection->getFileName());
        }

        if ($symbol->kind === Symbol::KIND_CLASS) {
            $reflection = new \ReflectionClass($symbol->name);
            return file_get_contents($reflection->getFileName());
        }

        throw new \LogicException("Cannot locate symbol [$symbol->name] source of kind [$symbol->kind]");
    }
}
