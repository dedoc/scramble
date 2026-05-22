<?php

namespace Dedoc\Scramble\Configuration;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ApiPath
{
    /**
     * @param  list<string>  $includes
     * @param  list<string>  $excludes
     */
    private function __construct(
        private readonly array $includes,
        private readonly array $excludes,
    ) {}

    /**
     * @param  string|array{include?: string|string[], exclude?: string|string[]}|null  $config
     */
    public static function from(mixed $config, string $default = 'api'): self
    {
        if ($config === null || is_string($config)) {
            return new self(self::normalizePrefixes($config ?? $default), []);
        }

        if (is_array($config) && (array_key_exists('include', $config) || array_key_exists('exclude', $config))) {
            return new self(
                self::normalizePrefixes($config['include'] ?? $default),
                self::normalizePrefixes($config['exclude'] ?? []),
            );
        }

        throw new InvalidArgumentException(
            'Invalid scramble.api_path config. Expected a string or an array with `include` and/or `exclude` keys.'
        );
    }

    /**
     * @return list<string>
     */
    private static function normalizePrefixes(mixed $prefixes): array
    {
        if ($prefixes === '' || $prefixes === null) {
            return [];
        }

        return array_values(array_filter(Arr::wrap($prefixes), fn ($prefix) => is_string($prefix) && $prefix !== ''));
    }

    public function matches(string $uri): bool
    {
        if ($this->includes !== [] && ! $this->matchesAny($uri, $this->includes)) {
            return false;
        }

        return ! $this->matchesAny($uri, $this->excludes);
    }

    public function stripPrefix(string $uri): string
    {
        if (! $this->usesSingleBase()) {
            return $uri;
        }

        return (string) Str::of($uri)
            ->replaceStart($this->includes[0], '')
            ->trim('/');
    }

    public function serverPath(): string
    {
        return $this->usesSingleBase() ? $this->includes[0] : '';
    }

    private function usesSingleBase(): bool
    {
        return count($this->includes) === 1 && ! str_contains($this->includes[0], '*');
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAny(string $uri, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*') ? Str::is($pattern, $uri) : ($uri === $pattern || Str::startsWith($uri, $pattern.'/'))) {
                return true;
            }
        }

        return false;
    }
}
