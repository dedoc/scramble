<?php

namespace Dedoc\Scramble\Infer\Reflection;

use Dedoc\Scramble\Infer\Contracts\ClassAutoResolvingDefinition as ClassAutoResolvingDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Contracts\SourceLocator;
use Dedoc\Scramble\Infer\DefinitionBuilders\ClassAutoResolvingDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\ClassDeferredDefinitionBuilder;
use Dedoc\Scramble\Infer\DefinitionBuilders\ClassReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\SourceLocators\AstLocator;
use Dedoc\Scramble\Infer\SourceLocators\ReflectionSourceLocator;
use Dedoc\Scramble\Infer\SourceLocators\StringSourceLocator;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class ReflectionClass
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
            $nativeReflection = new \ReflectionClass($name);
        } catch (\Throwable) {}

        $source = $nativeReflection?->getFileName();
        $sourceLocator = $source ? new ReflectionSourceLocator() : null;

        return new self($name, $sourceLocator, $index, $parser);
    }

    public function getDefinition(): ClassAutoResolvingDefinitionContract
    {
        $definitionBuilder = ! $this->sourceLocator // this is a built-in class
            ? new ClassReflectionDefinitionBuilder($this->name)
            : new ClassDeferredDefinitionBuilder($this->name, new AstLocator($this->parser, $this->sourceLocator));

        return new ClassAutoResolvingDefinition(
            definition: $definitionBuilder->build(),
            index: $this->index,
        );
    }
}
