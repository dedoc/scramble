<?php

namespace Dedoc\Scramble\Support\IndexBuilders;

/**
 * @template T of array<string, mixed>
 */
class Bag
{
    /**
     * @param  T  $data
     */
    public function __construct(
        public array $data = []
    ) {}

    /**
     * @template TKey of key-of<T>
     *
     * @param  TKey  $key
     * @param T[TKey] $value
     * @return $this
     */
    public function set(string $key, $value): self
    {
        $this->data[$key] = $value; // @phpstan-ignore-line

        return $this;
    }
}
