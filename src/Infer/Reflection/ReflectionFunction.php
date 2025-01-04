<?php

namespace Dedoc\Scramble\Infer\Reflection;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeDeferredDefinitionBuilder;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\SourceLocators\NullSourceLocator;
use Dedoc\Scramble\Infer\SourceLocators\ReflectionSourceLocator;
use Dedoc\Scramble\Infer\SourceLocators\SourceLocator;
use Dedoc\Scramble\Infer\SourceLocators\StringSourceLocator;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class ReflectionFunction
{
    private function __construct(
        public readonly string $name,
        public readonly SourceLocator $sourceLocator,
        public readonly Index $index,
        public readonly Parser $parser,
    )
    {

    }

    public static function createFromSource(string $name, string $source, ?Index $index = null, ?Parser $parser = null)
    {
        $index ??= app(Index::class);
        $parser ??= (new ParserFactory)->createForHostVersion();

        return new self($name, new StringSourceLocator($source), $index, $parser);
    }

    public static function createFromName(string $name, ?Index $index = null, ?Parser $parser = null)
    {
        $index ??= app(Index::class);
        $parser ??= (new ParserFactory)->createForHostVersion();

        $nativeReflection = null;
        try {
            $nativeReflection = new \ReflectionFunction($name);
        } catch (\Throwable) {}

        $source = $nativeReflection?->getFileName();
        $sourceLocator = $source ? new ReflectionSourceLocator() : new NullSourceLocator;

        return new self($name, $sourceLocator, $index, $parser);
    }

    public function getDefinition(): FunctionLikeDefinitionContract
    {
        $definitionBuilder = $this->sourceLocator instanceof NullSourceLocator
            ? new FunctionLikeReflectionDefinitionBuilder($this)
            : new FunctionLikeDeferredDefinitionBuilder($this, $this->parser);

        return $definitionBuilder->build();
    }
}
