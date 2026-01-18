<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Flow\ExpressionTypeInferrer;
use Dedoc\Scramble\Infer\Flow\Nodes;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class Scope
{
    /**
     * @internal
     *
     * @var array<string, array{line: int, type: Type}[]>
     */
    public array $variables = [];

    /**
     * @internal
     *
     * @var Node\Expr\CallLike[]
     */
    public array $calls = [];

    public Nodes $flowNodes;

    private ExpressionTypeInferrer $expressionTypeInferrer;

    public function __construct(
        public Index $index,
        public NodeTypesResolver $nodeTypesResolver,
        public ScopeContext $context,
        public FileNameResolver $nameResolver,
        public ?Scope $parentScope = null,
    ) {
        $this->flowNodes = new Nodes;
        $this->expressionTypeInferrer = new ExpressionTypeInferrer($this, $this->nodeTypesResolver);
    }

    public function getFlowNodes(): Nodes
    {
        return $this->flowNodes;
    }

    public function getType(Node $node): Type
    {
//        if (! $node instanceof Node\Expr) {
//            return new UnknownType;
//        }

        return $this->expressionTypeInferrer->infer(
            expr: $node,
            variableTypeGetter: fn (Node\Expr\Variable $n) => $this->getVariableType($n),
        );
    }

    // @todo: Move to some helper, Scope should be passed as a dependency.
    /**
     * @param  array<Node\Arg|Node\VariadicPlaceholder>  $args
     * @return array<string, Type>
     */
    public function getArgsTypes(array $args)
    {
        return collect($args)
            ->filter(fn ($arg) => $arg instanceof Node\Arg)
            ->mapWithKeys(function (Node\Arg $arg, $index) {
                $type = $this->getType($arg->value);
                if ($parsedPhpDoc = $arg->getAttribute('parsedPhpDoc')) {
                    $type->setAttribute('docNode', $parsedPhpDoc);
                }

                if (! $arg->unpack) {
                    return [$arg->name ? $arg->name->name : $index => $type];
                }

                if (! $type instanceof ArrayType && ! $type instanceof KeyedArrayType) {
                    return [$arg->name ? $arg->name->name : $index => $type]; // falling back, but not sure if we should. Maybe some DTO is needed to represent unpacked arg type?
                }

                if ($type instanceof ArrayType) {
                    /*
                     * For example, when passing something that is array, but shape is unknown
                     * $a = foo(...array_keys($bar));
                     */
                    return [$arg->name ? $arg->name->name : $index => $type]; // falling back, but not sure if we should. Maybe some DTO is needed to represent unpacked arg type?
                }

                return collect($type->items)
                    ->mapWithKeys(fn (ArrayItemType_ $item, $i) => [
                        $item->key ?: $index + $i => $item->value,
                    ])
                    ->all();
            })
            ->all();
    }

    public function setType(Node $node, Type $type): Type
    {
        $this->nodeTypesResolver->setType($node, $type);

        return $type;
    }

    public function createChildScope(?ScopeContext $context = null): Scope
    {
        return new Scope(
            $this->index,
            $this->nodeTypesResolver,
            $context ?: $this->context,
            $this->nameResolver,
            $this,
        );
    }

    /** @return TemplateType[] */
    private function getContextTemplates(): array
    {
        return [
            ...($this->classDefinition()?->templateTypes ?: []),
            ...($this->functionDefinition()?->type->templates ?: []),
            ...($this->parentScope?->getContextTemplates() ?: []),
        ];
    }

    public function makeConflictFreeTemplateName(string $name): string
    {
        $scopeDuplicateTemplates = collect($this->getContextTemplates())
            ->pluck('name')
            ->unique()
            ->values()
            ->filter(fn ($n) => preg_match('/^'.$name.'(\d*)?$/m', $n) === 1) // @phpstan-ignore argument.type
            ->all();

        return $name.($scopeDuplicateTemplates ? count($scopeDuplicateTemplates) : '');
    }

    /** @phpstan-assert-if-true !null $this->classDefinition() */
    public function isInClass(): bool
    {
        return (bool) $this->context->classDefinition;
    }

    public function classDefinition(): ?ClassDefinition
    {
        return $this->context->classDefinition;
    }

    public function functionDefinition(): ?FunctionLikeDefinition
    {
        return $this->context->functionDefinition;
    }

    /** @phpstan-assert-if-true !null $this->functionDefinition() */
    public function isInFunction(): bool
    {
        return (bool) $this->context->functionDefinition;
    }

    public function addVariableType(int $line, string $name, Type $type): void
    {
        if (! isset($this->variables[$name])) {
            $this->variables[$name] = [];
        }

        $this->variables[$name][] = compact('line', 'type');
    }

    private function getVariableType(Node\Expr\Variable $node): Type
    {
        if (! is_string($node->name)) {
            return new UnknownType('Cannot infer type of variable: non-string variable name not supported yet.');
        }

        $line = $node->getAttribute('startLine', 0);

        $definitions = $this->variables[$node->name] ?? [];

        $type = new UnknownType;
        foreach ($definitions as $definition) {
            if ($definition['line'] > $line) {
                return $type;
            }
            $type = $definition['type'];
        }

        return $type;
    }

    /**
     * @internal
     *
     * @return Node\Expr\CallLike[]
     */
    public function getMethodCalls(): array
    {
        return $this->calls;
    }
}
