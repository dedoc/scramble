<?php

namespace Dedoc\Scramble\Infer\Services;

use Illuminate\Support\Arr;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
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

    public function parseContent(string $content): FileParserResult
    {
        return $this->cache[md5($content)] ??= new FileParserResult(
            $statements = Arr::wrap($this->parser->parse($content)),
            new FileNameResolver(tap(new NameContext(new Throwing), fn (NameContext $nc) => $nc->startNamespace()))
        );
    }
}
