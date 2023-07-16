<?php

namespace Dedoc\Scramble\Infer\Reflector;

use Dedoc\Scramble\Infer\Services\FileParser;
use Illuminate\Support\Str;
use PhpParser\NameContext;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use ReflectionClass;

class ClassReflector
{
    private static array $cache = [];

    private ?NameContext $nameContext = null;

    private function __construct(
        private FileParser $parser,
        private string $className,
    ) {
    }

    public function getMethod(string $name)
    {
        return $this->methods[$name] ??= MethodReflector::make($this->className, $name);
    }

    public static function make(string $className): static
    {
        return static::$cache[$className] ??= new static(
            FileParser::getInstance(),
            $className,
        );
    }

    public function getReflection(): ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    public function getNameContext(): NameContext
    {
        if (! $this->nameContext) {
            $content = file_get_contents($this->getReflection()->getFileName());

            preg_match(
                '/(class|enum|interface|trait)\s+?(.*?)\s+?{/m',
                $content,
                $matches,
            );

            $firstMatchedClassLikeString = $matches[0] ?? '';

            $code = Str::before($content, $firstMatchedClassLikeString);

            $re = '/(namespace|use) ([.\s\S]*?);/m';
            preg_match_all($re, $code, $matches);

            $code = "<?php\n".implode("\n", $matches[0]);

            $nodes = $this->parser->parseContent($code)->getStatements();

            $traverser = new NodeTraverser;
            $traverser->addVisitor($nameResolver = new NameResolver);
            $traverser->traverse($nodes);

            $this->nameContext = $nameResolver->getNameContext();
        }

        return $this->nameContext;
    }
}
