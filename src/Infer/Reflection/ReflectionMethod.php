<?php

namespace Dedoc\Scramble\Infer\Reflection;

use ReflectionClass;
use ReflectionMethod as BaseReflectionMethod;

class ReflectionMethod extends BaseReflectionMethod
{
    /**
     * If the method is declared in trait, returns the reflection class of the trait.
     * @return ReflectionClass|false
     * @throws \ReflectionException
     */
    public function getDeclaringTrait(): ReflectionClass|false
    {
        $reflectionClass = $this->getDeclaringClass();

        if ($this->isDeclaredIn($reflectionClass)) {
            return false;
        }

        foreach (class_uses_recursive($reflectionClass->name) as $traitName) {
            $reflectionTrait = new ReflectionClass($traitName);

            if (! $this->isDeclaredIn($reflectionTrait)) {
                continue;
            }

            return $reflectionTrait;
        }

        return false;
    }

    private function isDeclaredIn(ReflectionClass $class): bool
    {
        $reflectionMethodFileName = $this->getFileName();
        $reflectionMethodStartLine = $this->getStartLine();

        $classFileName = $class->getFileName();
        $classStartLine = $class->getStartLine();
        $classEndLine = $class->getEndLine();

        if (! $reflectionMethodFileName || ! $classFileName) {
            return false;
        }

        return $reflectionMethodStartLine > $classStartLine && $reflectionMethodStartLine < $classEndLine;
    }
}
