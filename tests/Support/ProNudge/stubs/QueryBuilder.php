<?php

namespace Spatie\QueryBuilder;

class QueryBuilder
{
    public static function for(mixed $subject): self
    {
        return new self;
    }

    public function allowedFilters(mixed ...$filters): self
    {
        return $this;
    }

    public function get(): array
    {
        return [];
    }
}
