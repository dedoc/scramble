<?php

namespace Dedoc\Scramble\Support\Generator;

class UniqueNameOptions
{
    public function __construct(
        public readonly ?string $eloquent,
        public readonly array $unique,
        public readonly string $separator = '.',
        public readonly int $fallbackEloquentPartsCount = 2,
    ) {}

    public function getFallbackEloquent(): string
    {
        return collect($this->unique)
            ->take(-$this->fallbackEloquentPartsCount)
            ->join($this->separator);
    }

    public function getFallback(): string
    {
        return implode($this->separator, $this->unique);
    }
}
