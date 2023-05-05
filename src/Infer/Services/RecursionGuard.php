<?php

namespace Dedoc\Scramble\Infer\Services;

class RecursionGuard
{
    private $callIdsMap = [];

    public function call(string $id, callable $callback, callable $onInfiniteRecursion)
    {
        if (array_key_exists($id, $this->callIdsMap)) {
            return $onInfiniteRecursion();
        }

        try {
            $this->callIdsMap[$id] = true;

            return $callback();
        } finally {
            unset($this->callIdsMap[$id]);
        }
    }
}
