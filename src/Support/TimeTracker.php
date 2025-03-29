<?php

namespace Dedoc\Scramble\Support;

class TimeTracker
{
    public static $timers = [];

    public static $counts = [];

    public static function count(string $string)
    {
        static::$counts[$string] ??= 0;

        static::$counts[$string]++;
    }

    public static function time(string $string)
    {
        static::$timers[$string] ??= ['time' => 0, 'count' => 0];

        static::$timers[$string]['_runAt'] = microtime(true);
    }

    public static function timeEnd(string $string)
    {
        static::$timers[$string]['time'] += (microtime(true) - static::$timers[$string]['_runAt']) * 1000;
        static::$timers[$string]['count'] += 1;

        unset(static::$timers[$string]['_runAt']);
    }

    public static function recursiveSafeTime(string $string)
    {
        if (isset(static::$timers[$string]) && isset(static::$timers[$string]['_runAt'])) {
            // we're in recursion
            static::$timers[$string]['level'] ??= 0;
            static::$timers[$string]['level']++;

            return;
        }

        static::$timers[$string] ??= ['time' => 0, 'count' => 0];

        static::$timers[$string]['_runAt'] = microtime(true);
    }

    public static function recursiveSafeTimeEnd(string $string)
    {
        if (isset(static::$timers[$string]['level']) && static::$timers[$string]['level'] >= 1) {
            // we're in recursion
            static::$timers[$string]['level']--;

            return;
        }

        static::$timers[$string]['time'] += (microtime(true) - static::$timers[$string]['_runAt']) * 1000;
        static::$timers[$string]['count'] += 1;

        unset(static::$timers[$string]['_runAt']);
    }

    public static function measure(string $name, callable $cb)
    {
        static::time($name);

        try {
            return $cb();
        } finally {
            static::timeEnd($name);
        }
    }
}
