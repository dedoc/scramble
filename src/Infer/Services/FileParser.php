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
        return $this->cache[$path] ??= $this->resolveNames(
            $this->parser->parse(file_get_contents($path)),
        );
    }

    public function parseContent(string $content): array
    {
        return $this->cache[md5($content)] ??= $this->resolveNames(
            $this->parser->parse($content),
        );
    }

    private function resolveNames($statements): array
    {
        $statements = Arr::wrap($statements);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($nameResolver = new NameResolver());
        $traverser->addVisitor(new PhpDocResolver(
            new FileNameResolver($nameResolver->getNameContext()),
        ));
        $traverser->traverse($statements);

        return $statements;
    }
}
