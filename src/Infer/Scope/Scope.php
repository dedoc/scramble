<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\SimpleTypeGetters\BooleanNotTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\CastTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ClassConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ScalarTypeGetter;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class Scope
{
    public Index $index;

    public NodeTypesResolver $nodeTypesResolver;

    public ScopeContext $context;

    public ?Scope $parentScope;

    /**
     * @var array<string, array{line: int, type: Type}[]>
     */
    public array $variables = [];

    private FileNameResolver $namesResolver;

    public function __construct(
        Index $index,
        NodeTypesResolver $nodeTypesResolver,
        ScopeContext $context,
        FileNameResolver $namesResolver,
        ?Scope $parentScope = null)
    {
        $this->index = $index;
        $this->nodeTypesResolver = $nodeTypesResolver;
        $this->context = $context;
        $this->namesResolver = $namesResolver;
        $this->parentScope = $parentScope;
    }

    public function getType(Node $node)
    {
        if ($node instanceof Node\Scalar) {
            return (new ScalarTypeGetter)($node);
        }

        if ($node instanceof Node\Expr\Cast) {
            return (new CastTypeGetter)($node);
        }

        if ($node instanceof Node\Expr\ConstFetch) {
            return (new ConstFetchTypeGetter)($node);
        }

        if ($node instanceof Node\Expr\ClassConstFetch) {
            return (new ClassConstFetchTypeGetter)($node, $this);
        }

        if ($node instanceof Node\Expr\BooleanNot) {
            return (new BooleanNotTypeGetter)($node);
        }

        if ($node instanceof Node\Expr\Variable && $node->name === 'this') {
            return $this->context->class;
        }

        if ($node instanceof Node\Expr\Variable) {
            return $this->getVariableType($node) ?: new UnknownType;
        }

        $type = $this->nodeTypesResolver->getType($node);

        if (! $type instanceof UnknownType) {
            return $type;
        }

        if ($this->nodeTypesResolver->hasType($node)) { // For case when the unknown type was in node type resolver.
            return $type;
        }

        if ($node instanceof Node\Expr\MethodCall) {
            // Only string method names support.
            if (! $node->name instanceof Node\Identifier) {
                return $type;
            }

            return $this->setType(
                $node,
                new MethodCallReferenceType($this->getType($node->var), $node->name->name, $this->getArgsTypes($node->args)),
            );
        }

        if ($node instanceof Node\Expr\FuncCall) {
            // Only string func names support.
            if (! $node->name instanceof Node\Name) {
                return $type;
            }

            return $this->setType(
                $node,
                new CallableCallReferenceType($node->name->toString(), $this->getArgsTypes($node->args)),
            );
        }

        return $type;
    }

    private function getArgsTypes(array $args)
    {
        return collect($args)
            ->filter(fn ($arg) => $arg instanceof Node\Arg)
            ->keyBy(fn (Node\Arg $arg, $index) => $arg->name ? $arg->name->name : $index)
            ->map(fn (Node\Arg $arg) => $this->getType($arg->value))
            ->toArray();
    }

    public function setType(Node $node, Type $type)
    {
        $this->nodeTypesResolver->setType($node, $type);

        return $type;
    }

    public function createChildScope(?ScopeContext $context = null, ?callable $namesResolver = null)
    {
        return new Scope(
            $this->index,
            $this->nodeTypesResolver,
            $context ?: $this->context,
            $namesResolver ?: $this->namesResolver,
            $this,
        );
    }

    public function isInClass()
    {
        return (bool) $this->context->class;
    }

    public function class(): ?ObjectType
    {
        return $this->context->class;
    }

    public function isInFunction()
    {
        return (bool) $this->context->function;
    }

    public function function(): ?FunctionType
    {
        return $this->context->function;
    }

    public function resolveName(string $name)
    {
        return ($this->namesResolver)($name);
    }

    public function addVariableType(int $line, string $name, Type $type)
    {
        if (! isset($this->variables[$name])) {
            $this->variables[$name] = [];
        }

        $this->variables[$name][] = compact('line', 'type');
    }

    private function getVariableType(Node\Expr\Variable $node)
    {
        $name = (string) $node->name;
        $line = $node->getAttribute('startLine', 0);

        $definitions = $this->variables[$name] ?? [];

        $type = new UnknownType;
        foreach ($definitions as $definition) {
            if ($definition['line'] > $line) {
                return $type;
            }
            $type = $definition['type'];
        }

        return $type;
    }
}
