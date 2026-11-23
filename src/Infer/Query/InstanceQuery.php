<?php

namespace Dedoc\Scramble\Infer\Query;

use Closure;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Collection;

class InstanceQuery
{
    /** @var list<Closure(TrackedInstance): bool> */
    private array $predicates = [];

    public function __construct(
        private readonly CallQuery $calls,
        private readonly string $class,
        private readonly ShallowTypeResolver $shallowTypeResolver,
    ) {}

    /** @param Closure(TrackedInstance): bool $predicate */
    public function where(Closure $predicate): self
    {
        $this->predicates[] = $predicate;

        return $this;
    }

    /** @return Collection<int, TrackedInstance> */
    public function get(int $depth = 1): Collection
    {
        $instances = [];

        foreach ($this->calls->get($depth) as $call) {
            if ($call->node()->isFirstClassCallable()) {
                continue;
            }

            $scope = $call->scope();
            $type = $this->shallowTypeResolver->resolve($scope, $scope->getType($call->node()));

            foreach ($this->objectTypes($type) as $member) {
                if (! $member->isInstanceOf($this->class)) {
                    continue;
                }

                $instance = new TrackedInstance($member, $call);

                foreach ($this->predicates as $predicate) {
                    if (! $predicate($instance)) {
                        continue 2;
                    }
                }

                $instances[] = $instance;
            }
        }

        return collect($instances);
    }

    /** @return iterable<ObjectType> */
    private function objectTypes(Type $type): iterable
    {
        if ($type instanceof Union) {
            foreach ($type->types as $member) {
                yield from $this->objectTypes($member);
            }
        } elseif ($type instanceof ObjectType) {
            yield $type;
        }
    }
}
