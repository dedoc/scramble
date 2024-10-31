<?php

namespace Dedoc\Scramble\Infer\Services;

class RecursionGuard
{
    private $callIdsMap = [];

    private static $callIds = [];

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

    public static function run($obj, callable $callback, callable $onInfiniteRecursion)
    {
        if (array_key_exists($id = spl_object_id($obj), static::$callIds)) {
            return $onInfiniteRecursion();
        }

        try {
            static::$callIds[$id] = true;

            return $callback();
        } finally {
            unset(static::$callIds[$id]);
        }
    }
}
