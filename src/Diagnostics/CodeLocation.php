<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use ReflectionClass;

class CodeLocation
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
    ) {}

    /**
     * Array items combine PHPDoc both from array item node, and value node.
     */
    public static function fromArrayItemType(ArrayItemType_ $type): ?self
    {
        /** @var PhpDocNode|null $arrayItemDocNode */
        $arrayItemDocNode = $type->getAttribute('docNode');
        /** @var PhpDocNode|null $valueDocNode */
        $valueDocNode = $type->value->getAttribute('docNode');

        $context = $arrayItemDocNode?->getAttribute('sourceClass')
            ?? $valueDocNode?->getAttribute('sourceClass');

        /*
         * In non-class context, the source class is null.
         */
        if (! $context) {
            return null;
        }

        $line = $arrayItemDocNode?->getAttribute('sourceLine')
            ?? $valueDocNode?->getAttribute('sourceLine')
            ?? 0;

        return new self(
            file: static::getFile($context),
            line: $line,
        );
    }

    private static function getFile(string $class): string
    {
        $class = new ReflectionClass($class);

        if (is_string($file = $class->getFileName())) {
            return $file;
        }

        throw new \RuntimeException("Cannot get file name for class [$class]");
    }

    public static function fromReflection(ReflectionClass $reflection): self
    {
        if (! is_string($file = $reflection->getFileName())) {
            throw new \RuntimeException("Cannot get file name for class [$reflection->name]");
        }

        return new self($file, $reflection->getStartLine());
    }
}
