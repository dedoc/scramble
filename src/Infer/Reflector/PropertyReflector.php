<?php

namespace Dedoc\Scramble\Infer\Reflector;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use ReflectionMethod;
use ReflectionProperty;

class PropertyReflector
{
    private static array $cache = [];

    private ?Property $properyNode = null;

    private function __construct(
        private FileParser $parser, public string $className, public string $name) {}

    public static function make(string $className, string $name)
    {
        return static::$cache["$className@$name"] = new static(
            app(FileParser::class), // ?
            $className,
            $name,
        );
    }

    public function getPropertyCode(): string
    {
        return $this->getPropertyNodeDeclarationSource();
    }

    public function getReflection(): ReflectionProperty
    {
        /**
         * \ReflectionMethod could've been used here, but for `\Closure::__invoke` it fails when constructed manually
         */
        return (new \ReflectionClass($this->className))->getProperty($this->name);
    }

    private function getPropertyNodeDeclarationSource()
    {
        $tokens = token_get_all($code = "<?php\n".$this->getClassReflector()->getSource());

        $tokens             = token_get_all($code);
        $inClass            = false;
        $braceLevel         = 0;
        $collect            = false;
        $inFunctionDecl     = false;
        $inParamList        = false;
        $paramParenDepth    = 0;
        $result             = '';

        foreach ($tokens as $t) {
            if (is_array($t)) {
                [$id, $text] = $t;
            } else {
                $id   = null;
                $text = $t;
            }

            // track entering the class declaration
            if ($id === T_CLASS) {
                $inClass = true;
            }

            // track braces to know when we enter/leave class or method bodies
            if ($inClass && $text === '{') {
                $braceLevel++;
            }
            if ($inClass && $text === '}') {
                $braceLevel--;
                if ($braceLevel === 0) {
                    // left the class entirely
                    $inClass = false;
                }
            }

            // detect start of a method declaration (so we can ignore its params)
            if ($inClass && $braceLevel === 1 && $id === T_FUNCTION) {
                $inFunctionDecl = true;
            }

            // when in a function decl, track parentheses
            if ($inFunctionDecl && $text === '(') {
                $inParamList     = true;
                $paramParenDepth = 1;
            } elseif ($inParamList && $text === '(') {
                $paramParenDepth++;
            } elseif ($inParamList && $text === ')') {
                $paramParenDepth--;
                if ($paramParenDepth === 0) {
                    // end of parameter list
                    $inParamList    = false;
                    $inFunctionDecl = false;
                }
            }

            // start collecting when we see the right variable,
            // but only if we're at top-level of class body and not inside a param list
            if ($inClass
                && !$collect
                && !$inParamList
                && $id === T_VARIABLE
                && $text === '$' . $this->name
                && $braceLevel === 1
            ) {
                $collect       = true;
                $result       .= $text;
                continue;
            }

            if ($collect) {
                // collect everything (comments, whitespace, punctuation, etc.)
                $result .= $text;

                // stop collecting at the semicolon that closes this property
                if ($text === ';' && $braceLevel === 1) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @todo: Think if this method can actually return `null` or it should fail.
     */
    public function getAstNode(): ?Node\Stmt\Property
    {
        if ($this->properyNode) {
            return $this->properyNode;
        }

        $propertySource = $this->getPropertyNodeDeclarationSource();

        if (! $propertySource) {
            return null;
        }

        $partialClass = <<<"EOD"
<?php
class Foo {
    public $propertySource
}
EOD;

        $statements = $this->parser->parseContent($partialClass)->getStatements();
        $node = (new NodeFinder)
            ->findFirst(
                $statements,
                fn (Node $node) => $node instanceof Property && !! collect($node->props)->first(fn (Node\PropertyItem $p) => $p->name->name === $this->name),
            );

        $traverser = new NodeTraverser;

        $traverser->addVisitor(new class($this->getClassReflector()->getNameContext()) extends NameResolver
        {
            public function __construct($nameContext)
            {
                parent::__construct();
                $this->nameContext = $nameContext;
            }

            public function beforeTraverse(array $nodes): ?array
            {
                return null;
            }
        });
        $traverser->addVisitor(new PhpDocResolver(
            new FileNameResolver($this->getClassReflector()->getNameContext()),
        ));

        $traverser->traverse([$node]);

        $this->properyNode = $node;

        return $node;
    }

    public function getClassReflector(): ClassReflector
    {
        return ClassReflector::make($this->getReflection()->class);
    }
}
