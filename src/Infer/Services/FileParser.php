<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use Illuminate\Support\Arr;
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
     * @var array<string, FileParserResult>
     */
    private array $cache = [];

    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function parse(string $path): FileParserResult
    {
        return $this->cache[$path] ??= new FileParserResult($this->resolveNames(
            $this->parser->parse(file_get_contents($path)),
        ));
    }

    public function parseContent(string $content): FileParserResult
    {
        return $this->cache[md5($content)] ??= new FileParserResult($this->resolveNames(
            $this->parser->parse($content),
        ));
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
