<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Components\TermsOfContentItem;
use Dedoc\Scramble\Exceptions\ConsoleRenderable;
use Dedoc\Scramble\Exceptions\RouteAware;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Console\Command;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;
use ReflectionClass;
use Throwable;

class LspCommand extends Command
{
    protected $signature = 'scramble:lsp {payload}';

    protected $description = 'Replies to LSP.';

    public function handle(): int
    {
        ['textDocument' => $textDocument, 'position' => $position] = json_decode($this->argument('payload'), true, flags: JSON_THROW_ON_ERROR);

        $path = parse_url($textDocument['uri'], PHP_URL_PATH);
        $class = $this->getClassNamespaceFromFile($path).'\\'.$this->getClassNameFromFile($path);

        $classDefinition = $this->getInfer()->analyzeClass($class);

        $methodName = $this->getMethodByPosition($classDefinition->name, $position);

        if (! $methodName) {
            return Command::SUCCESS;
        }

        $methodDefinition = $classDefinition->getMethodDefinition($methodName);

        if (! $methodDefinition->selfOutTypeBuilder) {
            return Command::SUCCESS;
        }

        $nodeInQuestion = $this->getNodeByPosition(
            file_get_contents($path),
            $methodDefinition->selfOutTypeBuilder->node,
            $position['line'],
            $position['character'],
        );

        if (! $nodeInQuestion) {
            return Command::SUCCESS;
        }

        if ($nodeInQuestion instanceof Node\FunctionLike) {
            $this->outputFunctionLike($classDefinition, $methodDefinition, $nodeInQuestion);

            return Command::SUCCESS;
        }

        if ($nodeInQuestion instanceof Node\Param) {
            $nodeInQuestion = $nodeInQuestion->var;
        }

        $type = Infer\Services\ReferenceTypeResolver::getInstance()->resolve(
            $methodDefinition->selfOutTypeBuilder->scope,
            $methodDefinition->selfOutTypeBuilder->scope->getType($nodeInQuestion),
        );

//        dd($type);

        echo '<?php '.$type->toString();
//        echo "\n".'var $original: '.$type->getOriginal()->toString();
//        echo "\n".'$node = '.(app(PrettyPrinter::class)->prettyPrint([$nodeInQuestion]));

        return Command::SUCCESS;
    }

    protected function getClassNamespaceFromFile(string $filePathName)
    {
        $src = file_get_contents($filePathName);

        $tokens = token_get_all($src);
        $count = count($tokens);
        $i = 0;
        $namespace = '';
        $namespace_ok = false;
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                // Found namespace declaration
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespace_ok = true;
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }
                break;
            }
            $i++;
        }
        if (!$namespace_ok) {
            return null;
        } else {
            return $namespace;
        }
    }

    protected function getClassNameFromFile(string $filePathName)
    {
        $php_code = file_get_contents($filePathName);

        $classes = array();
        $tokens = token_get_all($php_code);
        $count = count($tokens);
        for ($i = 2; $i < $count; $i++) {
            if ($tokens[$i - 2][0] == T_CLASS
                && $tokens[$i - 1][0] == T_WHITESPACE
                && $tokens[$i][0] == T_STRING
            ) {

                $class_name = $tokens[$i][1];
                $classes[] = $class_name;
            }
        }

        return $classes[0];
    }

    private function getInfer(): Infer
    {
        return app(Infer::class);
    }

    /**
     * @param string $className
     * @param array{line: int, character: int} $position
     */
    private function getMethodByPosition(string $className, array $position): ?string
    {
        $class = new ReflectionClass($className);
        ['line' => $line, 'character' => $character] = $position;

        foreach ($class->getMethods() as $method) {
            if ($method->getStartLine() <= $line + 1 && $method->getEndLine() >= $line + 1) {
                return $method->name;
            }
        }

        return null;
    }

    private function getNodeByPosition(string $source, ClassMethod $node, int $line, int $character)
    {
        $startFilePosition = $this->getFileCharacterPositionByLspPosition($source, $line, $character);

        $visitor = new class ($startFilePosition) extends NodeVisitorAbstract {
            public function __construct(
                private int $startFilePosition,
                public ?Node $foundNode = null,
            ) {}

            public function leaveNode(Node $node)
            {
                $isNodeInPosition = $node->getStartFilePos() <= $this->startFilePosition
                    && $node->getEndFilePos() >= $this->startFilePosition;

                if (! $isNodeInPosition) {
                    return null;
                }

                if ($node instanceof Node\Identifier) {
                    return null;
                }

                if ($node instanceof Node\Name) {
                    return null;
                }

                $this->foundNode = $node;

                return self::STOP_TRAVERSAL;
            }
        };

        $traverser = new NodeTraverser($visitor);

        $traverser->traverse([$node]);

        return $visitor->foundNode;
    }

    private function getFileCharacterPositionByLspPosition(string $source, int $line, int $character): int
    {
        $lines = explode("\n", $source);

        $all = array_reduce(
            array_slice($lines, 0, $line),
            fn ($acc, $l) => $acc + strlen($l) + 1, // +1 for \n
            0,
        );

        return $all + $character;
    }

    private function outputFunctionLike(Infer\Definition\ClassDefinition $classDefinition, ?Infer\Definition\FunctionLikeDefinition $methodDefinition, Node\FunctionLike|Node $nodeInQuestion): void
    {
        $templates = array_map(
            fn (TemplateType $tt) => $tt->toDefinitionString(),
            $methodDefinition->type->templates,
        );
        if ($templates) {
            $templates = "<".implode(', ', $templates).">";
        }

        $parameters = collect($methodDefinition->type->arguments)
            ->map(fn (Type $type, $name) => $type->toString().' $'.$name)
            ->implode(', ');

        $returnType = $methodDefinition->type->getReturnType();

        echo "<?php (method) ".class_basename($classDefinition->name)."@".$methodDefinition->type->name.$templates."(".$parameters."): ".$returnType->toString();
        if ($methodDefinition->type->exceptions) {
            echo "\nThrows: ";
            foreach ($methodDefinition->type->exceptions as $exception) {
                echo "\n".$exception->toString();
            }
        }
    }
}
