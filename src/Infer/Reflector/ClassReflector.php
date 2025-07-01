<?php

namespace Dedoc\Scramble\Infer\Reflector;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use PhpParser\NameContext;
use ReflectionClass;
use RuntimeException;

class ClassReflector
{
    private static array $cache = [];

    private ?NameContext $nameContext = null;

    private array $methods = [];

    private function __construct(
        private FileParser $parser,
        public readonly string $className,
    ) {}

    public function getMethod(string $name)
    {
        return $this->methods[$name] ??= MethodReflector::make($this->className, $name);
    }

    public static function make(string $className): static
    {
        return static::$cache[$className] ??= new static(
            FileParser::getInstance(),
            $className,
        );
    }

    public function getReflection(): ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    public function getNameContext(): NameContext
    {
        return $this->nameContext ??= FileNameResolver::createForFile($this->getReflection()->getFileName())->nameContext;
    }

    /**
     * @throws RuntimeException
     */
    public function getSource(): string
    {
        $refClass = $this->getReflection();

        $fileName = $refClass->getFileName();
        if ($fileName === false) {
            throw new RuntimeException("Class {$refClass->getName()} is internal or not user-defined.");
        }

        $startLine = $refClass->getStartLine();
        $endLine = $refClass->getEndLine();

        $lines = file($fileName);
        if ($lines === false) {
            throw new RuntimeException("Unable to read file: $fileName");
        }

        $classLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return implode('', $classLines);
    }
}
