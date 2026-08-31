<?php

namespace Dedoc\Scramble\Support;

class Measure
{
    /** @var array<string, int> */
    private static array $startedAt = [];

    /** @var array<string, int> */
    private static array $depths = [];

    /** @var array<string, int> */
    private static array $durations = [];

    /** @var array<string, array<string, mixed>> */
    private static array $records = [];

    public static function start(string $name): void
    {
        if (! isset(self::$startedAt[$name])) {
            self::$startedAt[$name] = hrtime(true);
        }

        self::$depths[$name] = (self::$depths[$name] ?? 0) + 1;
    }

    public static function end(string $name): void
    {
        if (! isset(self::$startedAt[$name])) {
            return;
        }

        self::$depths[$name]--;

        if (self::$depths[$name] > 0) {
            return;
        }

        self::$durations[$name] = (self::$durations[$name] ?? 0) + hrtime(true) - self::$startedAt[$name];

        unset(self::$startedAt[$name], self::$depths[$name]);
    }

    public static function sourceFromFile(string|false $fileName): string
    {
        return $fileName !== false && str_contains(
            str_replace('\\', '/', $fileName),
            '/vendor/',
        ) ? 'vendor' : 'non_vendor';
    }

    public static function nameFor(mixed $value): string
    {
        return match (true) {
            is_string($value) => ltrim($value, '\\'),
            $value instanceof \Closure => self::closureName($value),
            is_array($value) => self::nameFor($value[0] ?? 'array').'::'.($value[1] ?? 'callback'),
            is_object($value) => $value::class,
            default => get_debug_type($value),
        };
    }

    private static function closureName(\Closure $closure): string
    {
        $reflection = new \ReflectionFunction($closure);
        $fileName = $reflection->getFileName() ?: 'internal';

        return "Closure@{$fileName}:{$reflection->getStartLine()}";
    }

    public static function record(string $name, mixed $value): void
    {
        $key = json_encode($value, JSON_THROW_ON_ERROR);

        self::$records[$name][$key] = $value;
    }

    /**
     * @return array<string, float>
     */
    public static function all(): array
    {
        return array_map(
            fn (int $duration) => $duration / 1_000_000,
            self::$durations,
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function records(): array
    {
        return array_map(
            fn (array $records) => array_values($records),
            self::$records,
        );
    }

    public static function reset(): void
    {
        self::$startedAt = [];
        self::$depths = [];
        self::$durations = [];
        self::$records = [];
    }
}
