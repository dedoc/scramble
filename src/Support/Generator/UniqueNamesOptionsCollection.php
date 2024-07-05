<?php

namespace Dedoc\Scramble\Support\Generator;

use Illuminate\Support\Collection;

class UniqueNamesOptionsCollection
{
    /**
     * @var array<string, UniqueNameOptions[]>
     */
    private array $eloquentNames = [];

    /**
     * @var array<string, UniqueNameOptions[]>
     */
    private array $fallbackEloquentNames = [];

    /**
     * @var array<string, UniqueNameOptions[]>
     */
    private array $fallbackNames = [];

    public function __construct(
        private Collection $names = new Collection,
    ) {}

    public function push(UniqueNameOptions $name)
    {
        $this->names->push($name);

        $this->eloquentNames[$name->eloquent] ??= [];
        $this->eloquentNames[$name->eloquent][] = $name;

        $this->fallbackEloquentNames[$name->getFallbackEloquent()] ??= [];
        $this->fallbackEloquentNames[$name->getFallbackEloquent()][] = $name;

        $this->fallbackNames[$name->getFallback()] ??= [];
        $this->fallbackNames[$name->getFallback()][] = $name;

        return $this;
    }

    public function getUniqueName(UniqueNameOptions $name, ?callable $onNotUniqueFallback = null): string
    {
        if ($name->eloquent && count($this->eloquentNames[$name->eloquent]) === 1) {
            return $name->eloquent;
        }

        if (count($this->fallbackEloquentNames[$name->getFallbackEloquent()]) === 1) {
            return $name->getFallbackEloquent();
        }

        if (count($this->fallbackNames[$name->getFallback()]) === 1) {
            return $name->getFallback();
        }

        return $onNotUniqueFallback ? $onNotUniqueFallback($name->getFallback()) : throw new \LogicException('Cannot retrieve a unique name');
    }
}
