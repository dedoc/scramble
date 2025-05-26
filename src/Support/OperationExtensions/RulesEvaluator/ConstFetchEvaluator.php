<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use PhpParser\Node;

class ConstFetchEvaluator
{
    /**
     * @param  array<string, ?string>  $classMap
     */
    public function __construct(public readonly array $classMap) {}

    public function evaluate(Node\Expr $expr, mixed $default = null): mixed
    {
        if (! $expr instanceof Node\Expr\ClassConstFetch) {
            return $default;
        }

        $className = $expr->class instanceof Node\Name
            ? $expr->class->toString()
            : null;

        $constName = $expr->name instanceof Node\Identifier
            ? $expr->name->toString()
            : null;

        if ($className && array_key_exists($className, $this->classMap)) {
            $className = $this->classMap[$className];
        }

        if (! $className || ! $constName) {
            return $default;
        }

        return constant("$className::$constName");
    }
}
