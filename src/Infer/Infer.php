<?php

namespace Dedoc\Scramble\Infer;

use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\NodeTraverser;
use ReflectionClass;

class Infer
{
    private FileParser $parser;

    private array $extensions;

    private array $handlers;

    private array $cache = [];

    public function __construct(FileParser $parser, array $extensions = [], array $handlers = [])
    {
        $this->parser = $parser;
        $this->extensions = $extensions;
        $this->handlers = $handlers;
    }

    public function analyzeClass(string $class): ObjectType
    {
        return $this->cache[$class] ??= $this->traverseClassAstAndInferType($class);
    }

    private function traverseClassAstAndInferType(string $class): ObjectType
    {
        $result = $this->parser->parse((new ReflectionClass($class))->getFileName());

        $traverser = new NodeTraverser;
        $traverser->addVisitor($inferer = new TypeInferer($result->getNamesResolver(), $this->extensions, $this->handlers));
        $traverser->traverse($result->getStatements());

        return $inferer->scope->getType($result->findFirstClass($class));
    }
}
