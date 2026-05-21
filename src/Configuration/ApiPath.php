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
        if ($config === null) {
            return self::fromParsed(
                self::normalizePrefixes($default),
                [],
            );
        }

        if (is_string($config)) {
            return self::fromParsed(
                self::normalizePrefixes($config),
                [],
            );
        }

        if (is_array($config)) {
            if (array_key_exists('include', $config) || array_key_exists('exclude', $config)) {
                return self::fromParsed(
                    self::normalizePrefixes($config['include'] ?? $default),
                    self::normalizePrefixes($config['exclude'] ?? []),
                );
            }

            throw new InvalidArgumentException(
                'Invalid scramble.api_path config. Expected a string or an array with `include` and/or `exclude` keys.'
            );
        }

        throw new InvalidArgumentException(
            'Invalid scramble.api_path config. Expected a string or an array with `include` and/or `exclude` keys.'
        );
    }

    /**
     * @param  list<string>  $includes
     * @param  list<string>  $excludes
     */
    private static function fromParsed(array $includes, array $excludes): self
    {
        return new self($includes, $excludes);
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
        return count($this->includes) === 1
            && strpbrk($this->includes[0], '*?') === false;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAny(string $uri, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($uri, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $uri, string $pattern): bool
    {
        if (strpbrk($pattern, '*?') === false) {
            return Str::startsWith($uri, $pattern);
        }

        if (str_contains($pattern, '?')) {
            return fnmatch($pattern, $uri);
        }

        return Str::is($pattern, $uri);
    }
}
