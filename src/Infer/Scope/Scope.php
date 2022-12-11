<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\SimpleTypeGetters\BooleanNotTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\CastTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ClassConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ScalarTypeGetter;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\PendingReturnType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class Scope
{
    public Index $index;

    public NodeTypesResolver $nodeTypesResolver;

    public PendingTypes $pending;

    public ScopeContext $context;

    public ?Scope $parentScope;

    /**
     * @var array<string, array{line: int, type: Type}[]>
     */
    public array $variables = [];

    public $namesResolver;

    public function __construct(
        Index $index,
        NodeTypesResolver $nodeTypesResolver,
        PendingTypes $pending,
        ScopeContext $context,
        callable $namesResolver,
        ?Scope $parentScope = null)
    {
        $this->index = $index;
        $this->nodeTypesResolver = $nodeTypesResolver;
        $this->pending = $pending;
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
            // @todo: remove as this should not happen - class must be always there
            return $this->context->class ?? new UnknownType;
        }

        if ($node instanceof Node\Expr\Variable) {
            return $this->getVariableType($node) ?: new UnknownType;
        }

        $type = $this->nodeTypesResolver->getType($node);

        if (! $type instanceof PendingReturnType && ! $type instanceof UnknownType) {
            return $type;
        }

        if ($this->nodeTypesResolver->hasType($node) && $type instanceof UnknownType) {
            return $type;
        }

        // Here we either don't have type for node cached (so we've got a freshly created
        // instance of UnknownType), or we have pending type that needs to be resolved.
        $type = $type instanceof PendingReturnType ? $type->getDefaultType() : $type;

        if ($node instanceof Node\Expr\MethodCall) {
            // Only string method names support.
            if (! $node->name instanceof Node\Identifier) {
                return $type;
            }

            $objectType = $this->getType($node->var);

            if ($this->isInFunction() && isset($objectType->methods[$node->name->name]) && count($objectType->methods[$node->name->name]->exceptions)) {
                $this->context->function->exceptions = [
                    ...$this->context->function->exceptions,
                    ...$objectType->methods[$node->name->name]->exceptions,
                ];
            }

            $methodCallType = $objectType->getMethodCallType($node->name->name);
            if ($methodCallType instanceof UnknownType && $type instanceof UnknownType) {
                $methodCallType = $type;
            }

            $type = $this->setType($node, $methodCallType);
        }

        if ($node instanceof Node\Expr\FuncCall) {
            // Only string func names support.
            if (! $node->name instanceof Node\Name) {
                return $type;
            }

            $fnType = $this->index->getFunctionType($node->name->toString());

            if ($this->isInFunction() && $fnType && count($fnType->exceptions)) {
                $this->context->function->exceptions = [
                    ...$this->context->function->exceptions,
                    ...$fnType->exceptions,
                ];
            }

            $type = $this->setType($node, $fnType ? $fnType->getReturnType() : $type);
        }

        return $type;
    }

    public function setType(Node $node, Type $type)
    {
        if (
            $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\FuncCall
            || $node instanceof Node\Expr\StaticCall
            || $node instanceof Node\Expr\NullsafeMethodCall
        ) {
            // While unknown type here may be legit and type is unknown, it also may mean
            // that there is enough information to get the type. But later there may be more
            // info. So we can wait for that info to appear.
            if ($type instanceof UnknownType) {
                // Here we also may know the class and the method being called, so we can
                // save them so later deps can be resolved. If this is needed, this code
                // to be moved in the node handlers that can produce this sort of results.
                // (fn calls and property accesses, others?)
                $type = new PendingReturnType($node, $type, $this);
            }
        }
        // Also, property fetch may be pending.

        $this->nodeTypesResolver->setType($node, $type);

        return $type;
    }

    public function createChildScope(?ScopeContext $context = null, ?callable $namesResolver = null)
    {
        return new Scope(
            $this->index,
            $this->nodeTypesResolver,
            $this->pending,
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
