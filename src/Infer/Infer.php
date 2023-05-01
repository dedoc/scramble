<?php

namespace Dedoc\Scramble\Infer;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\NodeTraverser;
use ReflectionClass;

class Infer
{
    private FileParser $parser;

    private array $extensions;

    private array $handlers;

    private array $cache = [];

    public Index $index;

    public function __construct(
        FileParser $parser,
        array $extensions = [],
        array $handlers = [],
    ) {
        $this->parser = $parser;
        $this->extensions = $extensions;
        $this->handlers = $handlers;
        $this->index = new Index;
    }

    public function analyzeClass(string $class): ObjectType
    {
        return $this->cache[$class] ??= $this->traverseClassAstAndInferType($class);
    }

    private function traverseClassAstAndInferType(string $class): ObjectType
    {
        $result = $this->parser->parse((new ReflectionClass($class))->getFileName());

        //        $index = new Index;

        $traverser = new NodeTraverser;
        $traverser->addVisitor($inferer = new TypeInferer(
            $result->getNamesResolver(),
            $this->extensions,
            $this->handlers,
            new ReferenceTypeResolver($this->index),
            $this->index,
        ));
        $traverser->traverse($result->getStatements());

        return $inferer->scope->getType($result->findFirstClass($class));
    }
}
