<?php

namespace Dedoc\Scramble\Infer\Reflection;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeAutoResolvingDefinition as FunctionLikeAutoResolvingDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Contracts\SourceLocator;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeAutoResolvingDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeDeferredDefinitionBuilder;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\SourceLocators\AstLocator;
use Dedoc\Scramble\Infer\SourceLocators\ReflectionSourceLocator;
use Dedoc\Scramble\Infer\SourceLocators\StringSourceLocator;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class ReflectionFunction
{
    private function __construct(
        public readonly string $name,
        public readonly ?SourceLocator $sourceLocator,
        public readonly Index $index,
        public readonly Parser $parser,
    ) {}

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
        } catch (\Throwable) {
        }

        $source = $nativeReflection?->getFileName();
        $sourceLocator = $source ? new ReflectionSourceLocator : null;

        return new self($name, $sourceLocator, $index, $parser);
    }

    public function getDefinition(): FunctionLikeAutoResolvingDefinitionContract
    {
        $definitionBuilder = ! $this->sourceLocator // this is a built-in fn
            ? new FunctionLikeReflectionDefinitionBuilder($this->name)
            : new FunctionLikeDeferredDefinitionBuilder($this->name, new AstLocator($this->parser, $this->sourceLocator));

        return new FunctionLikeAutoResolvingDefinition(
            $definitionBuilder->build(),
            index: $this->index,
        );
    }
}
