<?php

namespace Dedoc\Scramble\Infer\Query;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplateType;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class CallSite
{
    private bool $receiverResolved = false;

    private ?ObjectType $receiver = null;

    private bool $eventResolved = false;

    private MethodCallEvent|StaticMethodCallEvent|FunctionCallEvent|null $event = null;

    public function __construct(
        private readonly CallLike $node,
        private readonly Scope $scope,
        private readonly ArgumentTypeBag $arguments,
        private readonly ShallowTypeResolver $shallowTypeResolver,
    ) {}

    public function node(): CallLike
    {
        return $this->node;
    }

    public function scope(): Scope
    {
        return $this->scope;
    }

    public function arguments(): ArgumentTypeBag
    {
        return $this->arguments;
    }

    public function receiver(): ?ObjectType
    {
        if (! $this->receiverResolved) {
            $this->receiver = $this->resolveReceiver();
            $this->receiverResolved = true;
        }

        return $this->receiver;
    }

    public function event(): MethodCallEvent|StaticMethodCallEvent|FunctionCallEvent|null
    {
        if (! $this->eventResolved) {
            $this->event = $this->buildEvent();
            $this->eventResolved = true;
        }

        return $this->event;
    }

    private function resolveReceiver(): ?ObjectType
    {
        $node = $this->node;

        if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall) {
            $receiver = $this->shallowTypeResolver->resolve($this->scope, $this->scope->getType($node->var));

            return $receiver instanceof ObjectType ? $receiver : null;
        }

        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name) {
            $class = ReferenceTypeResolver::resolveClassName($this->scope, $node->class->toString());

            return $class ? new ObjectType($class) : null;
        }

        return null;
    }

    private function buildEvent(): MethodCallEvent|StaticMethodCallEvent|FunctionCallEvent|null
    {
        $node = $this->node;

        if ($node->isFirstClassCallable()) {
            return null;
        }

        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = $node->name->toString();
            $namespacedName = $node->name->getAttribute('namespacedName');

            if ($namespacedName instanceof Node\Name
                && (isset($this->scope->index->functionsDefinitions[$namespacedName->toString()])
                    || function_exists($namespacedName->toString()))) {
                $name = $namespacedName->toString();
            }

            return new FunctionCallEvent($name, $this->scope, $this->arguments);
        }

        if ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name) {
            $class = ReferenceTypeResolver::resolveClassName($this->scope, $node->class->toString());

            return $class ? $this->methodEvent(new ObjectType($class), '__construct') : null;
        }

        if (($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall)
            && $node->name instanceof Node\Identifier) {
            $receiver = ReferenceTypeResolver::getInstance()->resolve($this->scope, $this->scope->getType($node->var));

            if ($receiver instanceof TemplateType && $receiver->is) {
                $receiver = $receiver->is;
            }

            return $receiver instanceof ObjectType ? $this->methodEvent($receiver, $node->name->name) : null;
        }

        if ($node instanceof Node\Expr\StaticCall
            && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier
            && ($receiver = $this->receiver())) {
            if (in_array($node->class->toString(), StaticReference::KEYWORDS)
                && ! $this->scope->context->functionDefinition?->isStatic
                && $this->scope->context->classDefinition) {
                return $this->methodEvent(
                    new SelfType($this->scope->context->classDefinition->name),
                    $node->name->name,
                    $receiver->name,
                );
            }

            return new StaticMethodCallEvent($receiver->name, $node->name->name, $this->scope, $this->arguments);
        }

        return null;
    }

    private function methodEvent(ObjectType $instance, string $name, ?string $lookupClass = null): MethodCallEvent
    {
        $class = $lookupClass ?? $instance->name;
        $definition = $this->scope->index->getClassDefinition($class)?->getMethodDefinitionWithoutAnalysis($name)
            ?? app(LazyShallowReflectionIndex::class)->getClass($class)?->getMethod($name);

        return new MethodCallEvent(
            $instance,
            $name,
            $this->scope,
            $this->arguments,
            $definition->definingClassName ?? $class,
        );
    }

    public function phpDoc(): ?PhpDocNode
    {
        $phpDoc = $this->commentNode()->getAttribute('parsedPhpDoc', $this->node->getAttribute('parsedPhpDoc'));

        return $phpDoc instanceof PhpDocNode ? $phpDoc : null;
    }

    public function commentNode(): Node
    {
        $node = $this->node->getAttribute('scramble:commentNode');

        return $node instanceof Node ? $node : $this->node;
    }

    public function isConstructor(): bool
    {
        return $this->node() instanceof Node\Expr\New_;
    }
}
