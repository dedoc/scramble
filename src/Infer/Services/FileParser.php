<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use Illuminate\Support\Arr;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

/**
 * Parses PHP file and caches it. Used to ensure files parsed once, as
 * the parsing procedure is expensive.
 */
class FileParser
{
    /**
     * @var array<string, Stmt[]>
     */
    private array $cache = [];

    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function parse(string $path): array
    {
        return $this->cache[$path] ??= $this->traverseWithNamesResolution(
            $this->parser->parse(file_get_contents($path)),
        );
    }

    private function traverseWithNamesResolution($statements)
    {
        $statements = Arr::wrap($statements);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($nameResolver = new NameResolver());
        $traverser->addVisitor(new PhpDocResolver($nameResolver->getNameContext()));
        $traverser->traverse($statements);

        return $statements;
    }
}
