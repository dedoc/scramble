<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use Illuminate\Support\Arr;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
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

    public static function getInstance(): static
    {
        return app(static::class);
    }

    public function parse(string $path): FileParserResult
    {
        return $this->cache[$path] ??= new FileParserResult(
            $statements = Arr::wrap($this->parser->parse(file_get_contents($path))),
            $this->resolveNames($statements),
        );
    }

    public function parseContentNew(string $content): FileParserResult
    {
        return $this->cache[md5($content).'23'] ??= new FileParserResult(
            $statements = Arr::wrap($this->parser->parse($content)),
            new FileNameResolver(new NameContext(new Throwing()))
        );
    }

    private function resolveNames($statements): FileNameResolver
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor($nameResolver = new NameResolver());
        $traverser->addVisitor(new PhpDocResolver(
            $nameResolver = new FileNameResolver($nameResolver->getNameContext()),
        ));
        $traverser->traverse($statements);

        return $nameResolver;
    }
}
