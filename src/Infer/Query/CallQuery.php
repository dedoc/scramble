<?php

namespace Dedoc\Scramble\Infer\Query;

use Closure;
use Dedoc\Scramble\Infer\AutoResolvingArgumentTypeBag;
use Dedoc\Scramble\Infer\Definition\FunctionLikeAstDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Support\Type\ObjectType;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;

class CallQuery
{
    private ?string $receiverClass = null;

    private ?string $methodName = null;

    /** @var list<Closure(CallSite): bool> */
    private array $predicates = [];

    public function __construct(
        private readonly FunctionLikeAstDefinition $definition,
        private readonly ShallowTypeResolver $shallowTypeResolver,
    ) {}

    public function on(string $class): self
    {
        $this->receiverClass = $class;

        return $this;
    }

    /** @param array{string, string} $target */
    public function to(array $target): self
    {
        $this->receiverClass = $target[0];
        $this->methodName = $target[1];

        return $this;
    }

    /** @param Closure(CallSite): bool $predicate */
    public function where(Closure $predicate): self
    {
        $this->predicates[] = $predicate;

        return $this;
    }

    /**
     * @return Collection<int, CallSite>
     */
    public function get(int $depth = 1): Collection
    {
        if ($depth < 0 || $depth > 1) {
            throw new InvalidArgumentException('Call query depth must be 0 or 1.');
        }

        $calls = $this->callsIn($this->definition->getScope());
        $results = collect($calls->all());

        if ($depth === 1) {
            foreach ($calls as $call) {
                $callee = $this->resolveCallee($call);

                if ($callee) {
                    $results = $results->concat($this->callsIn($callee->getScope()));
                }
            }
        }

        if ($this->receiverClass !== null) {
            $results = $results
                ->filter(fn (CallSite $call) => $this->matchesMethod($call))
                ->filter(fn (CallSite $call) => $call->receiver()?->isInstanceOf($this->receiverClass) === true);
        }

        foreach ($this->predicates as $predicate) {
            $results = $results->filter($predicate);
        }

        return $results->values();
    }

    private function matchesMethod(CallSite $call): bool
    {
        if ($this->methodName === null) {
            return true;
        }

        $node = $call->node();

        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier
            && strcasecmp($node->name->name, $this->methodName) === 0;
    }

    /**
     * @return Collection<int, CallSite>
     */
    private function callsIn(Scope $scope): Collection
    {
        return collect($scope->getMethodCalls())
            ->map(fn (CallLike $node) => new CallSite(
                $node,
                $scope,
                new AutoResolvingArgumentTypeBag($scope, $scope->getArgsTypes($node->getRawArgs())),
                $this->shallowTypeResolver,
            ));
    }

    private function resolveCallee(CallSite $call): ?FunctionLikeAstDefinition
    {
        $node = $call->node();

        if ($node->isFirstClassCallable()) {
            return null;
        }

        if (! ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
            || ! $node->name instanceof Identifier) {
            return null;
        }

        $scope = $call->scope();
        $receiver = $call->receiver();

        if (! $receiver instanceof ObjectType) {
            return null;
        }

        $definition = $scope->index->getClass($receiver->name)
            ?->getMethodDefinition($node->name->name, $scope);

        return $definition instanceof FunctionLikeAstDefinition ? $definition : null;
    }
}
